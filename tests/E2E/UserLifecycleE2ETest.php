<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2ETestCase;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2EUserMapper;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestUser;
use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\UuidInterface;

/**
 * Drives one user through the full lifecycle via the service facade, exactly as an application would:
 * identifier-attribute bootstrap -> create (with role sync) -> lookup -> login/JWT -> update -> delete,
 * plus importing a user with an already-hashed (bcrypt) password, as done when migrating users into Keycloak.
 *
 * Every Keycloak object is created with a per-run name and removed in tearDownAfterClass(),
 * including the user-profile attribute and the protocol mapper added to the realm "profile" scope.
 */
#[Group('e2e')]
final class UserLifecycleE2ETest extends E2ETestCase
{
    private const string CLIENT_SCOPE = 'profile';

    private static KeycloakHttpClientInterface $httpClient;
    private static KeycloakServiceInterface $service;
    private static string $attributeName;
    private static string $claimName;
    private static string $password;
    private static ?UuidInterface $createdUserId = null;
    private static ?UuidInterface $importedUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$httpClient = self::createHttpClient();
        self::$attributeName = self::resourceName('external-id');
        self::$claimName = 'e2e_external_id_' . self::$runId;
        self::$password = 'E2e-' . self::$runId . '-Pa55word!';
        self::$service = self::createService(
            httpClient: self::$httpClient,
            mappers: [new E2EUserMapper(config: self::$config, localUserIdAttributeName: self::$attributeName)],
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$httpClient)) {
            return; // skipped: nothing was created
        }

        $realm = self::$config->realm;

        foreach ([self::$createdUserId, self::$importedUserId] as $userId) {
            if ($userId === null) {
                continue;
            }

            self::cleanup(static function () use ($realm, $userId): void {
                try {
                    self::$httpClient->deleteUser(new DeleteUserDto(realm: $realm, userId: $userId));
                } catch (KeycloakNotFoundException) {
                    // already deleted by the test itself
                }
            });
        }

        foreach (['reader', 'writer'] as $role) {
            self::cleanup(static function () use ($realm, $role): void {
                try {
                    self::$httpClient->deleteRole(
                        new DeleteRoleDto(realm: $realm, roleName: self::resourceName($role)),
                    );
                } catch (KeycloakNotFoundException) {
                    // role was never created
                }
            });
        }

        self::cleanup(static function () use ($realm): void {
            $scopeId = self::profileScopeId();
            foreach (self::identifierMappers() as $mapper) {
                $mapperId = $mapper->getId();
                if ($mapperId instanceof UuidInterface) {
                    self::$httpClient->deleteClientScopeProtocolMapper(
                        new DeleteClientScopeProtocolMapperDto(
                            realm: $realm,
                            clientScopeId: $scopeId,
                            protocolMapperId: $mapperId,
                        ),
                    );
                }
            }
        });

        self::cleanup(static function () use ($realm): void {
            $profile = self::$httpClient->getUserProfile(new GetUserProfileDto(realm: $realm));
            if ($profile->hasAttribute(self::$attributeName)) {
                self::$httpClient->deleteUserProfileAttribute(
                    new DeleteUserProfileAttributeDto(realm: $realm, attributeName: self::$attributeName),
                );
            }
        });
    }

    public function testIdentifierAttributeIsDeclaredAndExposedInJwt(): void
    {
        $ensure = new EnsureUserIdentifierAttributeDto(
            attributeName: self::$attributeName,
            displayName: 'E2E external id ' . self::$runId,
            createIfMissing: true,
            exposeInJwt: true,
            clientScopeName: self::CLIENT_SCOPE,
            jwtClaimName: self::$claimName,
        );

        self::$service->ensureUserIdentifierAttribute(realm: self::$config->realm, dto: $ensure);
        // Second call must be a no-op update, not a duplicate attribute or mapper.
        self::$service->ensureUserIdentifierAttribute(realm: self::$config->realm, dto: $ensure);

        $profile = self::$httpClient->getUserProfile(new GetUserProfileDto(realm: self::$config->realm));
        self::assertTrue($profile->hasAttribute(self::$attributeName));

        $mappers = self::identifierMappers();
        self::assertCount(1, $mappers);
        self::assertSame(self::$claimName, $mappers[0]->getConfigAsArray()['claim.name'] ?? null);
    }

    #[Depends('testIdentifierAttributeIsDeclaredAndExposedInJwt')]
    public function testCreateUserStoresIdentifierAndSynchronizesRoles(): void
    {
        $created = self::$service->createUser(self::localUser(), new PasswordDto(plainPassword: self::$password));
        self::$createdUserId = $created->getId();

        self::assertSame(self::resourceName('alice'), $created->getUsername());
        self::assertSame([self::localId()], $created->getAttributes()[self::$attributeName] ?? null);
    }

    #[Depends('testCreateUserStoresIdentifierAndSynchronizesRoles')]
    public function testUserIsFoundByLocalIdentifierAndByEmail(): void
    {
        // No Keycloak id on the local user: the service must resolve it through the identifier attribute.
        $found = self::$service->findUser(self::localUser());
        self::assertTrue($found->getId()->equals(self::$createdUserId));

        $matches = self::$service->searchUsers(
            new SearchUsersDto(realm: self::$config->realm, email: self::email(), exact: true),
        );
        self::assertCount(1, $matches);
        self::assertTrue($matches[0]->getId()->equals(self::$createdUserId));
    }

    #[Depends('testCreateUserStoresIdentifierAndSynchronizesRoles')]
    public function testLoginIssuesVerifiableTokenWithRolesAndIdentifierClaim(): void
    {
        $tokens = self::$service->loginUser(self::localUser(), self::$password);
        $payload = $tokens->getAccessToken()->getPayload();

        self::assertTrue($payload->getSub()->equals(self::requireUserId()));
        self::assertContains(self::resourceName('reader'), $payload->getRealmAccess()['roles']);
        self::assertSame(self::localId(), $payload->getClaim(self::$claimName));
        self::assertTrue(self::$service->verifyJwt($tokens->getAccessToken()->getRawToken()));

        $refreshToken = $tokens->getRefreshToken();
        self::assertNotNull($refreshToken);

        $refreshed = self::$service->refreshToken(
            new OidcTokenRequestDto(
                realm: self::$config->realm,
                clientId: self::$config->clientId,
                clientSecret: self::$config->clientSecret,
                refreshToken: $refreshToken,
                grantType: OidcGrantType::REFRESH_TOKEN,
            ),
        );
        self::assertTrue($payload->getSub()->equals($refreshed->getAccessToken()->getPayload()->getSub()));
    }

    #[Depends('testCreateUserStoresIdentifierAndSynchronizesRoles')]
    public function testLoginWithWrongPasswordIsAuthenticationFailure(): void
    {
        $this->expectException(KeycloakAuthenticationException::class);

        self::$service->loginUser(self::localUser(), 'wrong-' . self::$password);
    }

    #[Depends('testCreateUserStoresIdentifierAndSynchronizesRoles')]
    public function testUpdateUserChangesProfileAndReplacesRoles(): void
    {
        $before = self::localUser(keycloakId: self::$createdUserId?->toString());
        $after = self::localUser(
            keycloakId: self::$createdUserId?->toString(),
            firstName: 'Alicia',
            roles: [self::resourceName('writer')],
        );

        self::$service->updateUser($before, $after);

        $reloaded = self::$service->findUserById(self::$config->realm, self::requireUserId());
        self::assertSame('Alicia', $reloaded->getFirstName());
        self::assertSame([self::localId()], $reloaded->getAttributes()[self::$attributeName] ?? null);

        $payload = self::$service->loginUser($after, self::$password)->getAccessToken()->getPayload();
        $roles = $payload->getRealmAccess()['roles'];
        self::assertContains(self::resourceName('writer'), $roles);
        self::assertNotContains(self::resourceName('reader'), $roles);
    }

    #[Depends('testCreateUserStoresIdentifierAndSynchronizesRoles')]
    public function testDeleteUserRemovesItFromKeycloak(): void
    {
        self::$service->deleteUser(self::localUser(keycloakId: self::$createdUserId?->toString()));

        $this->expectException(KeycloakNotFoundException::class);
        self::$service->findUserById(self::$config->realm, self::requireUserId());
    }

    #[Depends('testIdentifierAttributeIsDeclaredAndExposedInJwt')]
    public function testImportedUserWithBcryptHashCanLogInWithOriginalPassword(): void
    {
        if (!self::$config->supportsPasswordHashing(HashAlgorithm::BCRYPT->value)) {
            self::markTestSkipped(
                'Keycloak has no "bcrypt" password-hashing provider (not part of stock Keycloak; '
                . 'needs a custom provider). Available: ' . implode(', ', self::$config->passwordHashingProviders),
            );
        }

        $plainPassword = 'Imported-' . self::$runId . '-Secret!';
        $importedUser = new ServiceTestUser(
            keycloakId: null,
            username: self::resourceName('imported'),
            email: self::resourceName('imported') . '@example.test',
            firstName: 'Imported',
            lastName: 'E2E',
            roles: [], // no role sync for this user
            id: 'imported-' . self::$runId,
        );

        // Same shape as a migration payload: the application only knows the hash, never the password.
        $created = self::$service->createUser(
            $importedUser,
            new PasswordDto(
                hashedPassword: password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                hashAlgorithm: HashAlgorithm::BCRYPT,
                hashIterations: 12,
                hashSalt: '',
            ),
        );
        self::$importedUserId = $created->getId();

        $tokens = self::$service->loginUser($importedUser, $plainPassword);
        self::assertTrue($tokens->getAccessToken()->getPayload()->getSub()->equals($created->getId()));

        try {
            self::$service->loginUser($importedUser, 'wrong-' . $plainPassword);
            self::fail('Login with a wrong password must be rejected for an imported user.');
        } catch (KeycloakAuthenticationException) {
            // expected
        }

        self::$service->deleteUser(
            new ServiceTestUser(
                keycloakId: $created->getKeycloakId(),
                username: $importedUser->getUsername(),
                email: $importedUser->getEmail(),
                id: $importedUser->getId(),
            ),
        );
    }

    /**
     * @param list<string>|null $roles
     */
    private static function localUser(
        ?string $keycloakId = null,
        string $firstName = 'Alice',
        ?array $roles = null,
    ): ServiceTestUser {
        return new ServiceTestUser(
            keycloakId: $keycloakId,
            username: self::resourceName('alice'),
            email: self::email(),
            emailVerified: true,
            firstName: $firstName,
            lastName: 'E2E',
            enabled: true,
            roles: $roles ?? [self::resourceName('reader')],
            id: self::localId(),
        );
    }

    private static function localId(): string
    {
        return 'local-' . self::$runId;
    }

    private static function email(): string
    {
        return self::resourceName('alice') . '@example.test';
    }

    private static function requireUserId(): UuidInterface
    {
        return self::$createdUserId ?? throw new \LogicException('User was not created.');
    }

    private static function profileScopeId(): UuidInterface
    {
        foreach (self::$httpClient->getClientScopes(new GetClientScopesDto(realm: self::$config->realm)) as $scope) {
            /** @var ClientScopeDto $scope */
            if ($scope->getName() === self::CLIENT_SCOPE && $scope->getId() !== null) {
                return $scope->getId();
            }
        }

        throw new \LogicException(sprintf('Client scope "%s" not found in realm.', self::CLIENT_SCOPE));
    }

    /**
     * @return list<ClientScopesProtocolMapperDto>
     */
    private static function identifierMappers(): array
    {
        $mappers = self::$httpClient->getClientScopeProtocolMappers(
            new GetClientScopeProtocolMappersDto(realm: self::$config->realm, clientScopeId: self::profileScopeId()),
        );

        return array_values(array_filter(
            $mappers,
            static fn (ClientScopesProtocolMapperDto $mapper): bool
                => ($mapper->getConfigAsArray()['user.attribute'] ?? null) === self::$attributeName,
        ));
    }
}
