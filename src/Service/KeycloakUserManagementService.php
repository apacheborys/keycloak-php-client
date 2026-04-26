<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakUserManagementService implements
    KeycloakUserManagementServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private LocalUserMapperResolver $mapperResolver,
        private ?LoggerInterface $logger = null,
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

        $mapper = $this->mapperResolver->resolveForUser(localUser: $localUser);
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

        $this->httpClient->createUser(
            dto: new CreateUserDto(
                profile: $profileDto,
                credentials: $credentials,
            )
        );

        $createdUser = $this->findSingleUserByEmail(
            realm: $realm,
            email: $profileDto->getEmail(),
            operation: 'createUser',
        );

        if ($plainPassword !== null) {
            $resetUserPasswordDto = new ResetUserPasswordDto(
                realm: $realm,
                user: $createdUser,
                type: KeycloakCredentialType::password(),
                value: $plainPassword,
                temporary: false,
            );

            $this->httpClient->resetPassword(dto: $resetUserPasswordDto);
        }

        return $createdUser;
    }

    #[Override]
    public function findUser(KeycloakUserInterface $localUser): KeycloakUser
    {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $localUser);
        $realm = $mapper->getRealm(localUser: $localUser);

        return $this->findUserById(
            realm: $realm,
            userId: Uuid::fromString($localUser->getKeycloakId()),
        );
    }

    #[Override]
    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser {
        if ($oldUserVersion->getKeycloakId() !== $newUserVersion->getKeycloakId()) {
            $this->debug(
                message: 'Update user failed: old and new Keycloak user identifiers are different.',
                context: [
                    'old_keycloak_user_id' => $oldUserVersion->getKeycloakId(),
                    'new_keycloak_user_id' => $newUserVersion->getKeycloakId(),
                ],
            );

            throw new LogicException('Old and new user versions must reference the same Keycloak user id.');
        }

        $mapper = $this->mapperResolver->resolveForUserPair(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
        $oldRealm = $mapper->getRealm(localUser: $oldUserVersion);
        $newRealm = $mapper->getRealm(localUser: $newUserVersion);
        if ($oldRealm !== $newRealm) {
            $this->debug(
                message: 'Update user failed: old and new user versions are mapped to different realms.',
                context: [
                    'old_keycloak_user_id' => $oldUserVersion->getKeycloakId(),
                    'new_keycloak_user_id' => $newUserVersion->getKeycloakId(),
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
        $this->assertMappedUserIdMatches(
            expectedUserId: $oldUserVersion->getKeycloakId(),
            mappedUserId: $dto->getUserId(),
            operation: 'updateUser',
        );

        $this->httpClient->updateUser(dto: $dto);

        return $this->findUserById(
            realm: $oldRealm,
            userId: $dto->getUserId(),
        );
    }

    #[Override]
    public function deleteUser(KeycloakUserInterface $user): void
    {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $user);
        $deleteDto = $mapper->prepareLocalUserForKeycloakUserDeletion(localUser: $user);

        $this->httpClient->deleteUser($deleteDto);
    }

    #[Override]
    public function findUserById(string $realm, UuidInterface $userId): KeycloakUser
    {
        try {
            return $this->httpClient->getUserById(
                dto: new GetUserByIdDto(
                    realm: $realm,
                    userId: $userId,
                ),
            );
        } catch (LogicException $exception) {
            $this->debug(
                message: 'User lookup failed: user was not found by identifier.',
                context: [
                    'realm' => $realm,
                    'expected_user_id' => $userId->toString(),
                ],
            );

            throw $exception;
        } catch (\Throwable $exception) {
            $this->debug(
                message: 'User lookup failed: user request by identifier returned an error.',
                context: [
                    'realm' => $realm,
                    'expected_user_id' => $userId->toString(),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }
    }

    private function findSingleUserByEmail(string $realm, string $email, string $operation): KeycloakUser
    {
        $searchDto = new SearchUsersDto(
            realm: $realm,
            email: $email,
            exact: true,
        );

        /** @var list<KeycloakUser> $users */
        $users = $this->httpClient->getUsers(dto: $searchDto);

        if (count($users) === 1) {
            return $users[0];
        }

        $this->debug(
            message: 'User lookup failed: user email search did not return exactly one result.',
            context: [
                'realm' => $realm,
                'operation' => $operation,
                'email' => $email,
                'found_user_ids' => array_values(array_map(
                    static fn (KeycloakUser $user): string => $user->getKeycloakId(),
                    $users,
                )),
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Expected exactly one user with email "%s" in realm "%s" during %s, got %d.',
                $email,
                $realm,
                $operation,
                count($users),
            )
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
                $mappedRealm,
            )
        );
    }

    private function assertMappedUserIdMatches(
        string $expectedUserId,
        UuidInterface $mappedUserId,
        string $operation,
    ): void {
        if ($expectedUserId === $mappedUserId->toString()) {
            return;
        }

        $this->debug(
            message: 'Mapper returned Keycloak user id different from local user identifier.',
            context: [
                'operation' => $operation,
                'expected_keycloak_user_id' => $expectedUserId,
                'mapped_keycloak_user_id' => $mappedUserId->toString(),
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Mapper Keycloak user id mismatch during %s. Expected "%s", got "%s".',
                $operation,
                $expectedUserId,
                $mappedUserId->toString(),
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
            throw new LogicException('Hash algorithm is required to build credentials data');
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
            throw new LogicException('Hashed password is required to build credentials data');
        }

        return $hashedPassword;
    }

    private function requireHashIterations(PasswordDto $passwordDto): int
    {
        $hashIterations = $passwordDto->getHashIterations();
        if ($hashIterations === null) {
            throw new LogicException('Hash iterations are required to build credentials data');
        }

        return $hashIterations;
    }

    private function requireHashSalt(PasswordDto $passwordDto): string
    {
        $hashSalt = $passwordDto->getHashSalt();
        if ($hashSalt === null) {
            throw new LogicException('Hash salt is required to build credentials data');
        }

        return $hashSalt;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        $this->logger?->debug(message: $message, context: $context);
    }
}
