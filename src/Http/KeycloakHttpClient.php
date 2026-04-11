<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    public const int REALM_LIST_TTL = 3600;
    public const int OPENID_CONFIGURATION_TTL = 86400;
    public const int JWK_BY_KID_TTL = 86400;

    public function __construct(
        private UserManagementHttpClientInterface $userManagement,
        private RoleManagementHttpClientInterface $roleManagement,
        private RealmSettingsManagementHttpClientInterface $realmSettingsManagement,
        private OidcInteractionHttpClientInterface $oidcInteraction,
        private string $baseUrl = '',
        private string $clientId = '',
        private ?CacheItemPoolInterface $cache = null,
        private int $realmListTtl = self::REALM_LIST_TTL,
        private int $openIdConfigurationTtl = self::OPENID_CONFIGURATION_TTL,
        private int $jwkByKidTtl = self::JWK_BY_KID_TTL,
    ) {
    }

    /**
     * @return list<KeycloakUser>
     */
    #[Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        return $this->userManagement->getUsers(dto: $dto);
    }

    #[Override]
    public function createUser(CreateUserDto $dto): void
    {
        $this->userManagement->createUser(dto: $dto);
    }

    #[Override]
    public function updateUser(UpdateUserDto $dto): void
    {
        $this->userManagement->updateUser(dto: $dto);
    }

    #[Override]
    public function deleteUser(DeleteUserDto $dto): void
    {
        $this->userManagement->deleteUser(dto: $dto);
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    #[Override]
    public function createRealm(array $payload): array
    {
        return $this->userManagement->createRealm(payload: $payload);
    }

    #[Override]
    public function getRoles(GetRolesDto $dto): array
    {
        return $this->roleManagement->getRoles(dto: $dto);
    }

    #[Override]
    public function getAvailableUserRoles(GetUserAvailableRolesDto $dto): array
    {
        return $this->roleManagement->getAvailableUserRoles(dto: $dto);
    }

    #[Override]
    public function createRole(CreateRoleDto $dto): void
    {
        $this->roleManagement->createRole(dto: $dto);
    }

    #[Override]
    public function deleteRole(DeleteRoleDto $dto): void
    {
        $this->roleManagement->deleteRole(dto: $dto);
    }

    #[Override]
    public function assignRolesToUser(AssignUserRolesDto $dto): void
    {
        $this->roleManagement->assignRolesToUser(dto: $dto);
    }

    #[Override]
    public function unassignRolesFromUser(AssignUserRolesDto $dto): void
    {
        $this->roleManagement->unassignRolesFromUser(dto: $dto);
    }

    #[Override]
    public function getUserProfile(GetUserProfileDto $dto): UserProfileDto
    {
        return $this->realmSettingsManagement->getUserProfile(dto: $dto);
    }

    #[Override]
    public function createUserProfileAttribute(CreateUserProfileAttributeDto $dto): UserProfileDto
    {
        return $this->realmSettingsManagement->createUserProfileAttribute(dto: $dto);
    }

    #[Override]
    public function updateUserProfileAttribute(UpdateUserProfileAttributeDto $dto): UserProfileDto
    {
        return $this->realmSettingsManagement->updateUserProfileAttribute(dto: $dto);
    }

    #[Override]
    public function deleteUserProfileAttribute(DeleteUserProfileAttributeDto $dto): UserProfileDto
    {
        return $this->realmSettingsManagement->deleteUserProfileAttribute(dto: $dto);
    }

    #[Override]
    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto
    {
        if ($allowToUseCache) {
            $cached = $this->readOpenIdConfigurationFromCache(realm: $realm);
            if ($cached instanceof OpenIdConfigurationDto) {
                return $cached;
            }
        }

        $openIdConfiguration = $this->oidcInteraction->getOpenIdConfiguration(
            realm: $realm,
        );

        $this->storeOpenIdConfigurationInCache(realm: $realm, dto: $openIdConfiguration);

        return $openIdConfiguration;
    }

    #[Override]
    public function getJwk(
        string $realm,
        string $kid,
        string $jwksUri,
        bool $allowToUseCache = true,
    ): ?JwkDto {
        if ($allowToUseCache) {
            $cached = $this->readJwkFromCache(realm: $realm, kid: $kid);
            if ($cached instanceof JwkDto) {
                return $cached;
            }
        }

        $jwk = $this->oidcInteraction->getJwk(
            kid: $kid,
            jwksUri: $jwksUri,
        );

        if ($jwk instanceof JwkDto) {
            $this->storeJwkInCache(realm: $realm, jwk: $jwk);
        }

        return $jwk;
    }

    #[Override]
    public function getJwks(string $realm, string $jwksUri): JwksDto
    {
        $jwks = $this->oidcInteraction->getJwks(jwksUri: $jwksUri);
        $this->storeJwksInCache(realm: $realm, jwks: $jwks);

        return $jwks;
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[Override]
    public function getAvailableRealms(): array
    {
        $cached = $this->readRealmsFromCache();
        if ($cached !== null) {
            return $cached;
        }

        $realms = $this->oidcInteraction->getAvailableRealms();
        $this->storeRealmsInCache(realms: $realms);

        return $realms;
    }

    #[Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $this->userManagement->resetPassword(dto: $dto);
    }

    #[Override]
    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->oidcInteraction->requestTokenByPassword(dto: $dto);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->oidcInteraction->refreshToken(dto: $dto);
    }

    private function readOpenIdConfigurationFromCache(string $realm): ?OpenIdConfigurationDto
    {
        if ($this->cache === null) {
            return null;
        }

        $cacheItem = $this->cache->getItem(key: $this->openIdConfigurationCacheKey(realm: $realm));
        if (!$cacheItem->isHit()) {
            return null;
        }

        $cachedValue = $cacheItem->get();
        if (!is_string(value: $cachedValue) || $cachedValue === '') {
            return null;
        }

        try {
            $data = json_decode(json: $cachedValue, associative: true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return null;
            }

            /** @var array<string, mixed> $data */
            return OpenIdConfigurationDto::fromArray(data: $data);
        } catch (Throwable) {
            return null;
        }
    }

    private function storeOpenIdConfigurationInCache(string $realm, OpenIdConfigurationDto $dto): void
    {
        if ($this->cache === null) {
            return;
        }

        /** @var string $serialized */
        $serialized = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);
        $cacheItem = $this->cache->getItem(key: $this->openIdConfigurationCacheKey(realm: $realm));
        $cacheItem->set(value: $serialized);
        $cacheItem->expiresAfter(time: $this->openIdConfigurationTtl);
        $this->cache->save(item: $cacheItem);
    }

    private function readJwkFromCache(string $realm, string $kid): ?JwkDto
    {
        if ($this->cache === null) {
            return null;
        }

        $cacheItem = $this->cache->getItem(key: $this->jwkByKidCacheKey(realm: $realm, kid: $kid));
        if (!$cacheItem->isHit()) {
            return null;
        }

        $cachedValue = $cacheItem->get();
        if (!is_string(value: $cachedValue) || $cachedValue === '') {
            return null;
        }

        try {
            $data = json_decode(json: $cachedValue, associative: true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return null;
            }

            /** @var array<string, mixed> $data */
            return JwkDto::fromArray(data: $data);
        } catch (Throwable) {
            return null;
        }
    }

    private function storeJwkInCache(string $realm, JwkDto $jwk): void
    {
        if ($this->cache === null) {
            return;
        }

        /** @var string $serialized */
        $serialized = json_encode(value: $jwk->toArray(), flags: JSON_THROW_ON_ERROR);
        $cacheItem = $this->cache->getItem(key: $this->jwkByKidCacheKey(realm: $realm, kid: $jwk->getKid()));
        $cacheItem->set(value: $serialized);
        $cacheItem->expiresAfter(time: $this->jwkByKidTtl);
        $this->cache->save(item: $cacheItem);
    }

    private function storeJwksInCache(string $realm, JwksDto $jwks): void
    {
        foreach ($jwks->getKeys() as $jwk) {
            $this->storeJwkInCache(realm: $realm, jwk: $jwk);
        }
    }

    /**
     * @return list<KeycloakRealm>|null
     */
    private function readRealmsFromCache(): ?array
    {
        if ($this->cache === null) {
            return null;
        }

        $cacheItem = $this->cache->getItem(key: $this->realmListCacheKey());
        if (!$cacheItem->isHit()) {
            return null;
        }

        $cachedValue = $cacheItem->get();
        if (!is_string(value: $cachedValue) || $cachedValue === '') {
            return null;
        }

        try {
            $decoded = json_decode(json: $cachedValue, associative: true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return null;
            }

            /** @var array<int, mixed> $decoded */
            $realms = [];
            foreach ($decoded as $realmData) {
                if (!is_array($realmData)) {
                    return null;
                }

                /** @var array<string, mixed> $realmData */
                $realms[] = KeycloakRealm::fromArray(data: $realmData);
            }

            return $realms;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param list<KeycloakRealm> $realms
     */
    private function storeRealmsInCache(array $realms): void
    {
        if ($this->cache === null) {
            return;
        }

        /** @var array<int, array<string, mixed>> $payload */
        $payload = array_map(
            static fn (KeycloakRealm $realm): array => $realm->jsonSerialize(),
            $realms,
        );

        /** @var string $serialized */
        $serialized = json_encode(value: $payload, flags: JSON_THROW_ON_ERROR);
        $cacheItem = $this->cache->getItem(key: $this->realmListCacheKey());
        $cacheItem->set(value: $serialized);
        $cacheItem->expiresAfter(time: $this->realmListTtl);
        $this->cache->save(item: $cacheItem);
    }

    private function openIdConfigurationCacheKey(string $realm): string
    {
        return 'keycloak.openid_configuration.' . sha1(string: $this->baseUrl . '|' . $realm);
    }

    private function jwkByKidCacheKey(string $realm, string $kid): string
    {
        return 'keycloak.jwk_by_kid.' . sha1(string: $this->baseUrl . '|' . $realm . '|' . $kid);
    }

    private function realmListCacheKey(): string
    {
        return 'keycloak.realm_list.' . sha1(string: $this->baseUrl . '|' . $this->clientId);
    }
}
