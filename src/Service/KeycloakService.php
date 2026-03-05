<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;

final readonly class KeycloakService implements KeycloakServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        /**
         * @var LocalKeycloakUserBridgeMapperInterface[] $mappers
         */
        private iterable $mappers,
        private ?LoggerInterface $logger = null,
        private bool $isRoleCreationAllowed = false,
    ) {
    }

    #[Override]
    public function createUser(
        KeycloakUserInterface $localUser,
        PasswordDto $passwordDto,
    ): KeycloakUser {
        $plainPassword = $passwordDto->getPlainPassword();

        if ($plainPassword === null) {
            $credentials = [
                new KeycloakCredential(
                    type: KeycloakCredentialType::password(),
                    credentialData: $this->buildCredentialData(passwordDto: $passwordDto),
                    secretData: $this->buildSecretData(passwordDto: $passwordDto)
                )
            ];
        } else {
            $credentials = [];
        }

        $mapper = $this->getMapperForLocalUser(localUser: $localUser);
        $realm = $mapper->getRealm(localUser: $localUser);
        $availableRoles = $this->httpClient->getRoles(
            dto: new GetRolesDto(realm: $realm),
        );

        $profileDto = $mapper->prepareLocalUserForKeycloakUserCreation(
            localUser: $localUser,
            availableRoles: $availableRoles,
        );

        $this->assertMappedRealmMatches(
            expectedRealm: $realm,
            mappedRealm: $profileDto->getRealm(),
            operation: 'createUser',
        );

        if ($this->isRoleCreationAllowed) {
            $availableRoles = $this->ensureRolesExistForRealm(
                realm: $realm,
                desiredRoles: $profileDto->getRoles(),
                availableRoles: $availableRoles,
            );
        }

        $rolesToAssign = $this->resolveRolesByName(
            desiredRoles: $profileDto->getRoles(),
            availableRoles: $availableRoles,
            strict: true,
        );

        $createUserDto = new CreateUserDto(
            profile: $profileDto,
            credentials: $credentials,
        );

        $this->httpClient->createUser(dto: $createUserDto);

        $searchDto = new SearchUsersDto(
            realm: $realm,
            email: $profileDto->getEmail(),
        );

        $result = $this->httpClient->getUsers(dto: $searchDto);

        if (count(value: $result) !== 1) {
            $this->debug(
                message: 'Create user failed: expected exactly one user after creation.',
                context: [
                    'email' => $profileDto->getEmail(),
                    'realm' => $realm,
                    'matched_users_count' => count(value: $result),
                ],
            );

            throw new LogicException(message: "Can't find just created user with email " . $profileDto->getEmail());
        }

        if ($plainPassword !== null) {
            $resetUserPasswordDto = new ResetUserPasswordDto(
                realm: $realm,
                user: $result[0],
                type: KeycloakCredentialType::password(),
                value: $plainPassword,
                temporary: false
            );

            $this->httpClient->resetPassword(dto: $resetUserPasswordDto);
        }

        if ($rolesToAssign !== []) {
            $this->httpClient->assignRolesToUser(
                dto: new AssignUserRolesDto(
                    realm: $realm,
                    userId: $result[0]->getId(),
                    roles: $rolesToAssign,
                ),
            );
        }

        return $result[0];
    }

    #[Override]
    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser {
        if ($oldUserVersion->getId() !== $newUserVersion->getId()) {
            $this->debug(
                message: 'Update user failed: old and new user identifiers are different.',
                context: [
                    'old_user_id' => $oldUserVersion->getId(),
                    'new_user_id' => $newUserVersion->getId(),
                ],
            );

            throw new LogicException('Old and new user versions must reference the same user id.');
        }

        $mapper = $this->getMapperForLocalUserPair(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion
        );
        $oldRealm = $mapper->getRealm(localUser: $oldUserVersion);
        $newRealm = $mapper->getRealm(localUser: $newUserVersion);
        if ($oldRealm !== $newRealm) {
            $this->debug(
                message: 'Update user failed: old and new user versions are mapped to different realms.',
                context: [
                    'old_user_id' => $oldUserVersion->getId(),
                    'new_user_id' => $newUserVersion->getId(),
                    'old_realm' => $oldRealm,
                    'new_realm' => $newRealm,
                ],
            );

            throw new LogicException('Old and new user versions must reference the same realm.');
        }

        $availableRoles = $this->httpClient->getRoles(
            dto: new GetRolesDto(realm: $oldRealm),
        );

        $dto = $mapper->prepareLocalUserDiffForKeycloakUserUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
            availableRoles: $availableRoles,
        );

        $this->assertMappedRealmMatches(
            expectedRealm: $oldRealm,
            mappedRealm: $dto->getRealm(),
            operation: 'updateUser',
        );

        $rolesToAssign = [];
        $rolesToUnassign = [];

        $desiredRoles = $dto->getProfile()->getRoles();
        if ($desiredRoles !== null) {
            if ($this->isRoleCreationAllowed) {
                $availableRoles = $this->ensureRolesExistForRealm(
                    realm: $oldRealm,
                    desiredRoles: $desiredRoles,
                    availableRoles: $availableRoles,
                );
            }

            $resolvedDesiredRoles = $this->resolveRolesByName(
                desiredRoles: $desiredRoles,
                availableRoles: $availableRoles,
                strict: true,
            );

            $currentRoleNames = $this->normalizeRoleNames(roleNames: $oldUserVersion->getRoles());
            $desiredRoleNames = $this->extractRoleNames(roles: $resolvedDesiredRoles);

            $roleNamesToAssign = array_values(array_diff($desiredRoleNames, $currentRoleNames));
            $roleNamesToUnassign = array_values(array_diff($currentRoleNames, $desiredRoleNames));

            $rolesToAssign = $this->resolveRolesByName(
                desiredRoles: $this->roleDtosFromNames(roleNames: $roleNamesToAssign),
                availableRoles: $availableRoles,
                strict: true,
            );

            if ($rolesToAssign !== []) {
                $availableForUser = $this->httpClient->getAvailableUserRoles(
                    dto: new GetUserAvailableRolesDto(
                        realm: $oldRealm,
                        userId: $dto->getUserId(),
                    ),
                );
                $rolesToAssign = $this->resolveRolesByName(
                    desiredRoles: $rolesToAssign,
                    availableRoles: $availableForUser,
                    strict: true,
                );
            }

            $rolesToUnassign = $this->resolveRolesByName(
                desiredRoles: $this->roleDtosFromNames(roleNames: $roleNamesToUnassign),
                availableRoles: $availableRoles,
                strict: false,
            );
        }

        $searchDto = new SearchUsersDto(
            realm: $oldRealm,
            email: $dto->getProfile()->getEmail(),
            exact: true,
        );

        $this->httpClient->updateUser(dto: $dto);
        if ($rolesToAssign !== []) {
            $this->httpClient->assignRolesToUser(
                dto: new AssignUserRolesDto(
                    realm: $oldRealm,
                    userId: $dto->getUserId(),
                    roles: $rolesToAssign,
                ),
            );
        }

        if ($rolesToUnassign !== []) {
            $this->httpClient->unassignRolesFromUser(
                dto: new AssignUserRolesDto(
                    realm: $oldRealm,
                    userId: $dto->getUserId(),
                    roles: $rolesToUnassign,
                ),
            );
        }

        /** @var array<int, KeycloakUser> $users */
        $users = $this->httpClient->getUsers(dto: $searchDto);

        foreach ($users as $user) {
            if ($user->getId() === $dto->getUserId()) {
                return $user;
            }
        }

        $this->debug(
            message: 'Update user failed: updated user was not found after update request.',
            context: [
                'expected_user_id' => $dto->getUserId(),
                'realm' => $oldRealm,
                'found_user_ids' => array_values(
                    array_map(
                        static fn (KeycloakUser $user): string => $user->getId(),
                        $users
                    )
                ),
            ],
        );

        throw new LogicException(
            message: "Can't find updated user with id " . $dto->getUserId() . ' in realm ' . $oldRealm
        );
    }

    #[Override]
    public function deleteUser(KeycloakUserInterface $user): void
    {
        $mapper = $this->getMapperForLocalUser(localUser: $user);
        $deleteDto = $mapper->prepareLocalUserForKeycloakUserDeletion(localUser: $user);

        $this->httpClient->deleteUser($deleteDto);
    }

    #[Override]
    public function getAvailableRealms(): array
    {
        return $this->httpClient->getAvailableRealms();
    }

    #[Override]
    public function verifyJwt(string $jwt): bool
    {
        $jwtFingerprint = sha1(string: $jwt);

        try {
            $token = JsonWebToken::fromRawToken(rawToken: $jwt);
        } catch (\Throwable $exception) {
            $this->debug(
                message: 'JWT verification failed: unable to parse token.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'exception_message' => $exception->getMessage(),
                ],
            );

            return false;
        }

        $temporalFailureReason = null;
        if (!$this->verifyTemporalClaims(token: $token, failureReason: $temporalFailureReason)) {
            $this->debug(
                message: 'JWT verification failed: temporal claims check failed.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'reason' => $temporalFailureReason,
                    'exp' => $token->getPayload()->getExp()->getTimestamp(),
                    'iat' => $token->getPayload()->getIat()->getTimestamp(),
                ],
            );

            return false;
        }

        $realm = $this->extractRealmFromIssuer(issuer: $token->getPayload()->getIss());
        if ($realm === null) {
            $this->debug(
                message: 'JWT verification failed: realm cannot be extracted from token issuer.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'issuer' => $token->getPayload()->getIss(),
                ],
            );

            return false;
        }

        try {
            $openIdConfiguration = $this->httpClient->getOpenIdConfiguration(realm: $realm);

            $jwk = $this->httpClient->getJwk(
                realm: $realm,
                kid: $token->getHeader()->getKid(),
                jwksUri: $openIdConfiguration->getJwksUri(),
            );
        } catch (\Throwable $exception) {
            $this->debug(
                message: 'JWT verification failed: unable to obtain OpenID configuration or JWK.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'realm' => $realm,
                    'kid' => $token->getHeader()->getKid(),
                    'exception_message' => $exception->getMessage(),
                ],
            );

            return false;
        }

        if (!$jwk instanceof JwkDto) {
            $this->debug(
                message: 'JWT verification failed: JWK was not found for token kid.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'realm' => $realm,
                    'kid' => $token->getHeader()->getKid(),
                ],
            );

            return false;
        }

        if (
            !$this->verifySignatureWithJwk(
                jwt: $jwt,
                algorithm: $token->getHeader()->getAlg(),
                jwk: $jwk
            )
        ) {
            $this->debug(
                message: 'JWT verification failed: signature verification returned false.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'realm' => $realm,
                    'kid' => $token->getHeader()->getKid(),
                    'alg' => $token->getHeader()->getAlg(),
                ],
            );

            return false;
        }

        $this->debug(
            message: 'JWT verification succeeded.',
            context: [
                'jwt_fingerprint' => $jwtFingerprint,
                'realm' => $realm,
                'kid' => $token->getHeader()->getKid(),
            ],
        );

        return true;
    }

    #[Override]
    public function loginUser(KeycloakUserInterface $user, string $plainPassword): OidcTokenResponseDto
    {
        $mapper = $this->getMapperForLocalUser(localUser: $user);
        $loginDto = $mapper->prepareLocalUserForKeycloakLoginUser(localUser: $user, plainPassword: $plainPassword);

        return $this->httpClient->requestTokenByPassword(dto: $loginDto);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->httpClient->refreshToken($dto);
    }

    private function getMapperForLocalUser(KeycloakUserInterface $localUser): LocalKeycloakUserBridgeMapperInterface
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->support(localUser: $localUser)) {
                return $mapper;
            }
        }

        $this->debug(
            message: 'Mapper resolution failed: mapper for local user was not found.',
            context: [
                'user_class' => $localUser::class,
                'user_id' => $localUser->getId(),
            ],
        );

        throw new LogicException(message: "Can't find proper mapper for " . $localUser::class);
    }

    private function getMapperForLocalUserPair(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): LocalKeycloakUserBridgeMapperInterface {
        foreach ($this->mappers as $mapper) {
            if (
                $mapper->support(localUser: $oldUserVersion)
                && $mapper->support(localUser: $newUserVersion)
            ) {
                return $mapper;
            }
        }

        $this->debug(
            message: 'Mapper resolution failed: mapper for user update pair was not found.',
            context: [
                'old_user_class' => $oldUserVersion::class,
                'old_user_id' => $oldUserVersion->getId(),
                'new_user_class' => $newUserVersion::class,
                'new_user_id' => $newUserVersion->getId(),
            ],
        );

        throw new LogicException(
            message: "Can't find proper mapper for update pair: "
                . $oldUserVersion::class
                . ' and '
                . $newUserVersion::class
        );
    }

    private function assertMappedRealmMatches(string $expectedRealm, string $mappedRealm, string $operation): void
    {
        if ($expectedRealm === $mappedRealm) {
            return;
        }

        $this->debug(
            message: 'Mapper returned realm different from mapper::getRealm().',
            context: [
                'operation' => $operation,
                'expected_realm' => $expectedRealm,
                'mapped_realm' => $mappedRealm,
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Mapper realm mismatch during %s. Expected "%s", got "%s".',
                $operation,
                $expectedRealm,
                $mappedRealm
            )
        );
    }

    private function buildCredentialData(PasswordDto $passwordDto): string
    {
        $hashContext = $this->buildHashContext(passwordDto: $passwordDto);

        /** @var string $credentialsData */
        $credentialsData = json_encode(
            value: [
                'algorithm' => $hashContext['algorithm'],
                'hashIterations' => $hashContext['hashIterations'],
            ],
            flags: JSON_THROW_ON_ERROR,
        );

        return $credentialsData;
    }

    private function buildSecretData(PasswordDto $passwordDto): string
    {
        $hashContext = $this->buildHashContext(passwordDto: $passwordDto);

        /** @var string $secretData */
        $secretData = json_encode(
            value: [
                'value' => $this->requireHashedPassword(passwordDto: $passwordDto),
                'salt' => $hashContext['salt'],
            ],
            flags: JSON_THROW_ON_ERROR,
        );

        return $secretData;
    }

    /**
     * @return array{algorithm: string, hashIterations: int, salt: string}
     */
    private function buildHashContext(PasswordDto $passwordDto): array
    {
        $hashAlgorithm = $passwordDto->getHashAlgorithm();
        if ($hashAlgorithm === null) {
            throw new LogicException("Hash algorithm is required to build credentials data");
        }

        return match ($hashAlgorithm) {
            HashAlgorithm::ARGON, HashAlgorithm::BCRYPT => [
                'algorithm' => $hashAlgorithm->value,
                'hashIterations' => $this->requireHashIterations(passwordDto: $passwordDto),
                'salt' => $this->requireHashSalt(passwordDto: $passwordDto),
            ],
            HashAlgorithm::MD5 => [
                'algorithm' => $hashAlgorithm->value,
                'hashIterations' => 1,
                'salt' => '',
            ],
        };
    }

    private function requireHashedPassword(PasswordDto $passwordDto): string
    {
        $hashedPassword = $passwordDto->getHashedPassword();
        if ($hashedPassword === null) {
            throw new LogicException("Hashed password is required to build credentials data");
        }

        return $hashedPassword;
    }

    private function requireHashIterations(PasswordDto $passwordDto): int
    {
        $hashIterations = $passwordDto->getHashIterations();
        if ($hashIterations === null) {
            throw new LogicException("Hash iterations are required to build credentials data");
        }

        return $hashIterations;
    }

    private function requireHashSalt(PasswordDto $passwordDto): string
    {
        $hashSalt = $passwordDto->getHashSalt();
        if ($hashSalt === null) {
            throw new LogicException("Hash salt is required to build credentials data");
        }

        return $hashSalt;
    }

    /**
     * @param list<RoleDto> $desiredRoles
     * @param list<RoleDto> $availableRoles
     * @return list<RoleDto>
     */
    private function ensureRolesExistForRealm(string $realm, array $desiredRoles, array $availableRoles): array
    {
        $availableByName = [];
        foreach ($availableRoles as $role) {
            $availableByName[$role->getName()] = $role;
        }

        $hasCreatedRoles = false;
        foreach ($this->normalizeRoles(roles: $desiredRoles) as $desiredRole) {
            $roleName = $desiredRole->getName();
            if (array_key_exists($roleName, $availableByName)) {
                continue;
            }

            $this->httpClient->createRole(
                dto: new CreateRoleDto(
                    realm: $realm,
                    role: $desiredRole,
                ),
            );
            $hasCreatedRoles = true;
            $this->debug(
                message: 'Role was created in Keycloak during role synchronization.',
                context: [
                    'realm' => $realm,
                    'role_name' => $roleName,
                ],
            );
        }

        if ($hasCreatedRoles) {
            return $this->httpClient->getRoles(
                dto: new GetRolesDto(realm: $realm),
            );
        }

        return $availableRoles;
    }

    /**
     * @param list<RoleDto> $desiredRoles
     * @param list<RoleDto> $availableRoles
     * @return list<RoleDto>
     */
    private function resolveRolesByName(array $desiredRoles, array $availableRoles, bool $strict): array
    {
        $availableByName = [];
        foreach ($availableRoles as $availableRole) {
            $availableByName[$availableRole->getName()] = $availableRole;
        }

        $resolvedRoles = [];
        foreach ($this->normalizeRoles(roles: $desiredRoles) as $desiredRole) {
            $resolvedRole = $availableByName[$desiredRole->getName()] ?? null;
            if ($resolvedRole instanceof RoleDto) {
                $resolvedRoles[] = $resolvedRole;
                continue;
            }

            if ($strict) {
                throw new LogicException(
                    message: sprintf(
                        'Role "%s" cannot be resolved in Keycloak available roles.',
                        $desiredRole->getName()
                    )
                );
            }

            $this->debug(
                message: 'Role cannot be resolved during non-strict role synchronization and will be skipped.',
                context: [
                    'role_name' => $desiredRole->getName(),
                ],
            );
        }

        return $resolvedRoles;
    }

    /**
     * @param list<string> $roleNames
     * @return list<string>
     */
    private function normalizeRoleNames(array $roleNames): array
    {
        $normalized = [];
        foreach ($roleNames as $roleName) {
            $trimmedRoleName = trim($roleName);
            if ($trimmedRoleName === '') {
                continue;
            }

            $normalized[] = $trimmedRoleName;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param list<RoleDto> $roles
     * @return list<string>
     */
    private function extractRoleNames(array $roles): array
    {
        return array_values(
            array_unique(
                array_map(
                    static fn (RoleDto $role): string => $role->getName(),
                    $roles,
                )
            )
        );
    }

    /**
     * @param list<string> $roleNames
     * @return list<RoleDto>
     */
    private function roleDtosFromNames(array $roleNames): array
    {
        return array_map(
            static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
            $this->normalizeRoleNames(roleNames: $roleNames),
        );
    }

    /**
     * @param list<RoleDto> $roles
     * @return list<RoleDto>
     */
    private function normalizeRoles(array $roles): array
    {
        $rolesByName = [];
        foreach ($roles as $role) {
            $rolesByName[$role->getName()] = $role;
        }

        return array_values($rolesByName);
    }

    private function verifyTemporalClaims(JsonWebToken $token, ?string &$failureReason = null): bool
    {
        $now = time();

        if ($token->getPayload()->getExp()->getTimestamp() <= $now) {
            $failureReason = 'token_expired';
            return false;
        }

        if ($token->getPayload()->getIat()->getTimestamp() > ($now + 300)) {
            $failureReason = 'token_issued_in_future';
            return false;
        }

        return true;
    }

    private function extractRealmFromIssuer(string $issuer): ?string
    {
        $path = parse_url(url: $issuer, component: PHP_URL_PATH);
        if (!is_string(value: $path) || $path === '') {
            return null;
        }

        $segments = explode(separator: '/', string: trim(string: $path, characters: '/'));

        foreach ($segments as $index => $segment) {
            if ($segment !== 'realms') {
                continue;
            }

            $realm = $segments[$index + 1] ?? null;
            if (!is_string($realm) || $realm === '') {
                return null;
            }

            return $realm;
        }

        return null;
    }

    private function verifySignatureWithJwk(string $jwt, string $algorithm, JwkDto $jwk): bool
    {
        $opensslAlgorithm = $this->resolveOpenSslAlgorithm(algorithm: $algorithm);
        if ($opensslAlgorithm === null) {
            return false;
        }

        $certificate = $jwk->getFirstCertificate();
        if ($certificate === null) {
            return false;
        }

        $pem = $this->buildCertificatePem(certificate: $certificate);
        $publicKey = openssl_pkey_get_public(public_key: $pem);

        if ($publicKey === false) {
            return false;
        }

        $parts = explode(separator: '.', string: $jwt);
        if (count(value: $parts) !== 3) {
            return false;
        }

        $signature = $this->decodeBase64Url(value: $parts[2]);
        if ($signature === null) {
            return false;
        }

        $input = $parts[0] . '.' . $parts[1];
        $result = openssl_verify(
            data: $input,
            signature: $signature,
            public_key: $publicKey,
            algorithm: $opensslAlgorithm
        );

        return $result === 1;
    }

    private function resolveOpenSslAlgorithm(string $algorithm): ?int
    {
        return match ($algorithm) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => null,
        };
    }

    private function buildCertificatePem(string $certificate): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(string: $certificate, length: 64, separator: "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function decodeBase64Url(string $value): ?string
    {
        $remainder = strlen(string: $value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat(string: '=', times: 4 - $remainder);
        }

        $decoded = base64_decode(string: strtr(string: $value, from: '-_', to: '+/'), strict: true);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        $this->logger?->debug(message: $message, context: $context);
    }
}
