<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceFactory;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2EEnvironment;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2ELocalUser;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2EUserMapper;
use Apacheborys\KeycloakPhpClient\Tests\Support\Cache\InMemoryCachePool;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18Client;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

/**
 * Base class for tests that run against a real Keycloak instance.
 *
 * Tests are skipped unless all KEYCLOAK_E2E_* variables are set (see E2EEnvironment).
 * Everything a test creates must be registered with one of the track*() methods so that
 * tearDown() removes it again, even when the test fails half-way.
 */
abstract class KeycloakE2ETestCase extends TestCase
{
    protected E2EEnvironment $environment;
    protected string $realm;
    protected KeycloakHttpClientInterface $httpClient;
    protected KeycloakServiceInterface $service;
    protected E2EUserMapper $mapper;

    /** @var list<UuidInterface> */
    private array $usersToDelete = [];
    /** @var list<string> */
    private array $roleNamesToDelete = [];
    /** @var list<string> */
    private array $clientScopeNamesToDelete = [];
    /** @var list<string> */
    private array $profileAttributesToDelete = [];
    /** @var list<array{0: string, 1: string}> client scope name, protocol mapper name */
    private array $protocolMappersToDelete = [];

    protected function setUp(): void
    {
        $environment = E2EEnvironment::fromEnvironment();
        if ($environment === null) {
            self::markTestSkipped(sprintf(
                'E2E tests need a real Keycloak. Missing environment variables: %s. See docs/testing-and-quality.md.',
                implode(', ', E2EEnvironment::missingVariables()),
            ));
        }

        $this->environment = $environment;
        $this->realm = $environment->realm;

        $this->httpClient = (new KeycloakHttpClientFactory())->create(
            config: $environment->clientConfig(),
            httpClient: new NativePsr18Client(),
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
            cache: new InMemoryCachePool(),
        );

        $this->mapper = new E2EUserMapper(
            realm: $environment->realm,
            clientId: $environment->clientId,
            clientSecret: $environment->clientSecret,
        );

        $this->service = (new KeycloakServiceFactory())->create(
            httpClient: $this->httpClient,
            mappers: [$this->mapper],
        );
    }

    protected function tearDown(): void
    {
        if (!isset($this->httpClient)) {
            return;
        }

        $failures = [];
        $attempt = function (string $what, callable $cleanup) use (&$failures): void {
            try {
                $cleanup();
            } catch (Throwable $exception) {
                $failures[] = $what . ': ' . $exception->getMessage();
            }
        };

        foreach ($this->protocolMappersToDelete as [$scopeName, $mapperName]) {
            $attempt("protocol mapper $mapperName", function () use ($scopeName, $mapperName): void {
                $scope = $this->findClientScopeByName($scopeName);
                $mapper = $scope === null ? null : $this->findProtocolMapperByName($scope, $mapperName);
                if ($scope?->getId() !== null && $mapper?->getId() !== null) {
                    $this->httpClient->deleteClientScopeProtocolMapper(new DeleteClientScopeProtocolMapperDto(
                        realm: $this->realm,
                        clientScopeId: $scope->getId(),
                        protocolMapperId: $mapper->getId(),
                    ));
                }
            });
        }
        foreach ($this->usersToDelete as $userId) {
            $attempt("user $userId", fn () => $this->httpClient->deleteUser(
                new DeleteUserDto(realm: $this->realm, userId: $userId),
            ));
        }
        foreach ($this->roleNamesToDelete as $roleName) {
            $attempt("role $roleName", fn () => $this->httpClient->deleteRole(
                new DeleteRoleDto(realm: $this->realm, roleName: $roleName),
            ));
        }
        foreach ($this->clientScopeNamesToDelete as $scopeName) {
            $attempt("client scope $scopeName", function () use ($scopeName): void {
                $scope = $this->findClientScopeByName($scopeName);
                if ($scope?->getId() !== null) {
                    $this->httpClient->deleteClientScope(
                        new DeleteClientScopeDto(realm: $this->realm, clientScopeId: $scope->getId()),
                    );
                }
            });
        }
        foreach ($this->profileAttributesToDelete as $attributeName) {
            $attempt("profile attribute $attributeName", fn () => $this->httpClient->deleteUserProfileAttribute(
                new DeleteUserProfileAttributeDto(realm: $this->realm, attributeName: $attributeName),
            ));
        }

        $this->usersToDelete = [];
        $this->roleNamesToDelete = [];
        $this->clientScopeNamesToDelete = [];
        $this->profileAttributesToDelete = [];
        $this->protocolMappersToDelete = [];

        // Deleting an entity the test already removed (or never managed to create) is expected; anything else is worth seeing.
        $realFailures = array_filter(
            $failures,
            static fn (string $f): bool => !str_contains($f, '404') && !str_contains(strtolower($f), 'not found'),
        );
        if ($realFailures !== []) {
            fwrite(STDERR, "\n[e2e cleanup] " . implode("\n[e2e cleanup] ", $realFailures) . "\n");
        }
    }

    protected function uniqueName(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }

    /**
     * @param list<string> $roles
     * @param array<string, string> $extraAttributes
     */
    protected function newLocalUser(array $roles = [], array $extraAttributes = []): E2ELocalUser
    {
        $username = $this->uniqueName('e2e-user');

        return new E2ELocalUser(
            id: 'local-' . Uuid::uuid4()->toString(),
            username: $username,
            email: $username . '@e2e.example.com',
            firstName: 'E2E',
            lastName: 'User',
            roles: $roles,
            extraAttributes: $extraAttributes,
        );
    }

    protected function trackUser(UuidInterface|string $userId): void
    {
        $this->usersToDelete[] = $userId instanceof UuidInterface ? $userId : Uuid::fromString($userId);
    }

    protected function trackRole(string $roleName): void
    {
        $this->roleNamesToDelete[] = $roleName;
    }

    protected function trackClientScope(string $scopeName): void
    {
        $this->clientScopeNamesToDelete[] = $scopeName;
    }

    protected function trackProfileAttribute(string $attributeName): void
    {
        $this->profileAttributesToDelete[] = $attributeName;
    }

    protected function trackProtocolMapper(string $scopeName, string $mapperName): void
    {
        $this->protocolMappersToDelete[] = [$scopeName, $mapperName];
    }

    protected function findClientScopeByName(string $name): ?ClientScopeDto
    {
        foreach ($this->httpClient->getClientScopes(new GetClientScopesDto(realm: $this->realm)) as $scope) {
            if ($scope->getName() === $name) {
                return $scope;
            }
        }

        return null;
    }

    protected function findProtocolMapperByName(ClientScopeDto $scope, string $name): ?ClientScopesProtocolMapperDto
    {
        $scopeId = $scope->getId();
        if ($scopeId === null) {
            return null;
        }

        $mappers = $this->httpClient->getClientScopeProtocolMappers(
            new GetClientScopeProtocolMappersDto(realm: $this->realm, clientScopeId: $scopeId),
        );
        foreach ($mappers as $mapper) {
            if ($mapper->getName() === $name) {
                return $mapper;
            }
        }

        return null;
    }

    protected function findRoleByName(string $name): ?RoleDto
    {
        foreach ($this->httpClient->getRoles(new GetRolesDto(realm: $this->realm)) as $role) {
            if ($role->getName() === $name) {
                return $role;
            }
        }

        return null;
    }

    protected function findUserByUsername(string $username): ?KeycloakUser
    {
        $users = $this->httpClient->getUsers(
            new SearchUsersDto(realm: $this->realm, username: $username, exact: true),
        );

        return $users[0] ?? null;
    }

    /**
     * @param list<RoleDto> $roles
     * @return list<string>
     */
    protected static function roleNames(array $roles): array
    {
        return array_map(static fn (RoleDto $role): string => $role->getName(), $roles);
    }
}
