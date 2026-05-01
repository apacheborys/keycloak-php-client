<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\Service\Internal\KeycloakUserLookup;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserIdentifier;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakUserManagementService implements
    KeycloakUserManagementServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private LocalUserMapperResolver $mapperResolver,
        private KeycloakUserLookup $userLookup,
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

    /**
     * `SearchUsersDto` is treated here as a query object rather than a raw transport payload.
     *
     * @return list<KeycloakUser>
     */
    #[Override]
    public function searchUsers(SearchUsersDto $dto): array
    {
        return $this->httpClient->getUsers(dto: $dto);
    }

    #[Override]
    public function findUser(KeycloakUserInterface $localUser): KeycloakUser
    {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $localUser);
        $realm = $mapper->getRealm(localUser: $localUser);

        return $this->userLookup->resolveUser(
            realm: $realm,
            localUser: $localUser,
            localUserIdAttributeName: $mapper->getLocalUserIdAttributeName(localUser: $localUser),
            operation: 'findUser',
        );
    }

    #[Override]
    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser {
        $this->assertLocalUserIdsMatch(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
            operation: 'updateUser',
        );
        $this->assertKnownKeycloakUserIdsMatch(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
            operation: 'updateUser',
        );

        $mapper = $this->mapperResolver->resolveForUserPair(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
        $oldRealm = $mapper->getRealm(localUser: $oldUserVersion);
        $newRealm = $mapper->getRealm(localUser: $newUserVersion);
        $localUserIdAttributeName = $this->resolveLocalUserIdAttributeNameForPair(
            mapper: $mapper,
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
            operation: 'updateUser',
        );
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

        $lookupUser = $this->selectLookupUserForUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
        $resolvedUserId = $this->userLookup->resolveUserId(
            realm: $oldRealm,
            localUser: $lookupUser,
            localUserIdAttributeName: $localUserIdAttributeName,
            operation: 'updateUser',
        );

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
        $this->assertMappedLocalUserIdMatches(
            expectedLocalUserId: $newUserVersion->getId(),
            mappedLocalUserId: $dto->getLocalUserId(),
            operation: 'updateUser',
        );
        $this->assertOptionalMappedUserIdMatches(
            expectedUserId: $resolvedUserId,
            mappedUserId: $dto->getUserId(),
            operation: 'updateUser',
        );

        $transportDto = new UpdateUserDto(
            realm: $dto->getRealm(),
            profile: $dto->getProfile(),
            userId: $resolvedUserId,
            localUserId: $dto->getLocalUserId(),
        );

        $this->httpClient->updateUser(dto: $transportDto);

        return $this->findUserById(
            realm: $oldRealm,
            userId: $resolvedUserId,
        );
    }

    #[Override]
    public function deleteUser(KeycloakUserInterface $user): void
    {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $user);
        $realm = $mapper->getRealm(localUser: $user);
        $resolvedUserId = $this->userLookup->resolveUserId(
            realm: $realm,
            localUser: $user,
            localUserIdAttributeName: $mapper->getLocalUserIdAttributeName(localUser: $user),
            operation: 'deleteUser',
        );
        $deleteDto = $mapper->prepareLocalUserForKeycloakUserDeletion(localUser: $user);

        $this->assertMappedRealmMatches(
            expectedRealm: $realm,
            mappedRealm: $deleteDto->getRealm(),
            operation: 'deleteUser',
        );
        $this->assertMappedLocalUserIdMatches(
            expectedLocalUserId: $user->getId(),
            mappedLocalUserId: $deleteDto->getLocalUserId(),
            operation: 'deleteUser',
        );
        $this->assertOptionalMappedUserIdMatches(
            expectedUserId: $resolvedUserId,
            mappedUserId: $deleteDto->getUserId(),
            operation: 'deleteUser',
        );

        $this->httpClient->deleteUser(
            new DeleteUserDto(
                realm: $deleteDto->getRealm(),
                userId: $resolvedUserId,
                localUserId: $deleteDto->getLocalUserId(),
            )
        );
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

        $users = $this->searchUsers(dto: $searchDto);

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

    private function assertOptionalMappedUserIdMatches(
        UuidInterface $expectedUserId,
        ?UuidInterface $mappedUserId,
        string $operation,
    ): void {
        if ($mappedUserId === null) {
            return;
        }

        if ($expectedUserId->equals($mappedUserId)) {
            return;
        }

        $this->debug(
            message: 'Mapper returned Keycloak user id different from local user identifier.',
            context: [
                'operation' => $operation,
                'expected_keycloak_user_id' => $expectedUserId->toString(),
                'mapped_keycloak_user_id' => $mappedUserId->toString(),
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Mapper Keycloak user id mismatch during %s. Expected "%s", got "%s".',
                $operation,
                $expectedUserId->toString(),
                $mappedUserId->toString(),
            )
        );
    }

    private function selectLookupUserForUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
    ): KeycloakUserInterface {
        if ($newUserVersion->getKeycloakId() !== null || $oldUserVersion->getKeycloakId() === null) {
            return $newUserVersion;
        }

        return $oldUserVersion;
    }

    private function assertLocalUserIdsMatch(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        string $operation,
    ): void {
        $oldLocalUserId = LocalUserIdentifier::normalize($oldUserVersion->getId());
        $newLocalUserId = LocalUserIdentifier::normalize($newUserVersion->getId());

        if ($oldLocalUserId === $newLocalUserId) {
            return;
        }

        $this->debug(
            message: 'Update user failed: old and new local user identifiers are different.',
            context: [
                'operation' => $operation,
                'old_local_user_id' => LocalUserIdentifier::logValue($oldUserVersion->getId()),
                'new_local_user_id' => LocalUserIdentifier::logValue($newUserVersion->getId()),
            ],
        );

        throw new LogicException('Old and new user versions must reference the same local user id.');
    }

    private function assertKnownKeycloakUserIdsMatch(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        string $operation,
    ): void {
        $oldKeycloakId = $oldUserVersion->getKeycloakId();
        $newKeycloakId = $newUserVersion->getKeycloakId();
        if ($oldKeycloakId === null || $newKeycloakId === null || $oldKeycloakId === $newKeycloakId) {
            return;
        }

        $this->debug(
            message: 'Update user failed: old and new Keycloak user identifiers are different.',
            context: [
                'operation' => $operation,
                'old_local_user_id' => LocalUserIdentifier::logValue($oldUserVersion->getId()),
                'new_local_user_id' => LocalUserIdentifier::logValue($newUserVersion->getId()),
                'old_keycloak_user_id' => $oldKeycloakId,
                'new_keycloak_user_id' => $newKeycloakId,
            ],
        );

        throw new LogicException('Old and new user versions must reference the same Keycloak user id.');
    }

    private function resolveLocalUserIdAttributeNameForPair(
        LocalKeycloakUserBridgeMapperInterface $mapper,
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        string $operation,
    ): string {
        $oldAttributeName = $mapper->getLocalUserIdAttributeName(localUser: $oldUserVersion);
        $newAttributeName = $mapper->getLocalUserIdAttributeName(localUser: $newUserVersion);

        if (trim($oldAttributeName) === '' || trim($newAttributeName) === '') {
            $this->debug(
                message: 'Mapper returned an empty local user id attribute name.',
                context: [
                    'operation' => $operation,
                    'old_local_user_id' => LocalUserIdentifier::logValue($oldUserVersion->getId()),
                    'new_local_user_id' => LocalUserIdentifier::logValue($newUserVersion->getId()),
                    'old_attribute_name' => $oldAttributeName,
                    'new_attribute_name' => $newAttributeName,
                ],
            );

            throw new LogicException('Mapper local user id attribute name must not be empty.');
        }

        if ($oldAttributeName === $newAttributeName) {
            return $newAttributeName;
        }

        $this->debug(
            message: 'Mapper returned different local user id attribute names for update pair.',
            context: [
                'operation' => $operation,
                'old_local_user_id' => LocalUserIdentifier::logValue($oldUserVersion->getId()),
                'new_local_user_id' => LocalUserIdentifier::logValue($newUserVersion->getId()),
                'old_attribute_name' => $oldAttributeName,
                'new_attribute_name' => $newAttributeName,
            ],
        );

        throw new LogicException('Old and new user versions must use the same local user id attribute name.');
    }

    private function assertMappedLocalUserIdMatches(
        int|string|UuidInterface $expectedLocalUserId,
        int|string|UuidInterface|null $mappedLocalUserId,
        string $operation,
    ): void {
        if ($mappedLocalUserId === null) {
            $this->debug(
                message: 'Mapper did not provide local user id metadata.',
                context: [
                    'operation' => $operation,
                    'expected_local_user_id' => LocalUserIdentifier::logValue($expectedLocalUserId),
                ],
            );

            throw new LogicException(
                message: sprintf('Mapper local user id is missing during %s.', $operation)
            );
        }

        $expected = LocalUserIdentifier::normalize($expectedLocalUserId);
        $mapped = LocalUserIdentifier::normalize($mappedLocalUserId);

        if ($expected === $mapped) {
            return;
        }

        $this->debug(
            message: 'Mapper returned local user id different from local user identifier.',
            context: [
                'operation' => $operation,
                'expected_local_user_id' => LocalUserIdentifier::logValue($expectedLocalUserId),
                'mapped_local_user_id' => LocalUserIdentifier::logValue($mappedLocalUserId),
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Mapper local user id mismatch during %s. Expected "%s", got "%s".',
                $operation,
                $expected,
                $mapped,
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
