<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\Http\Internal\AccessTokenProvider;
use Apacheborys\KeycloakPhpClient\Http\Internal\ClientScopeManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Http\Internal\OidcInteractionHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\RealmSettingsManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\RoleManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\UserManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class KeycloakHttpClientFactory
{
    private const int DEFAULT_REALM_LIST_TTL = 3600;
    private const int DEFAULT_OPENID_CONFIGURATION_TTL = 86400;
    private const int DEFAULT_JWK_BY_KID_TTL = 86400;

    public function create(
        KeycloakClientConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?CacheItemPoolInterface $cache = null
    ): KeycloakHttpClientInterface {
        $httpCore = new KeycloakHttpCore(
            baseUrl: $config->getBaseUrl(),
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );

        $accessTokenProvider = new AccessTokenProvider(
            httpCore: $httpCore,
            clientRealm: $config->getClientRealm(),
            clientId: $config->getClientId(),
            clientSecret: $config->getClientSecret(),
            cache: $cache,
        );

        $userManagement = new UserManagementHttpClient(
            httpCore: $httpCore,
            accessTokenProvider: $accessTokenProvider,
        );

        $roleManagement = new RoleManagementHttpClient(
            httpCore: $httpCore,
            accessTokenProvider: $accessTokenProvider,
        );

        $clientScopeManagement = new ClientScopeManagementHttpClient(
            httpCore: $httpCore,
            accessTokenProvider: $accessTokenProvider,
        );

        $oidcInteraction = new OidcInteractionHttpClient(
            httpCore: $httpCore,
            accessTokenProvider: $accessTokenProvider,
        );

        $realmSettingsManagement = new RealmSettingsManagementHttpClient(
            httpCore: $httpCore,
            accessTokenProvider: $accessTokenProvider,
        );

        return new KeycloakHttpClient(
            userManagement: $userManagement,
            roleManagement: $roleManagement,
            clientScopeManagement: $clientScopeManagement,
            realmSettingsManagement: $realmSettingsManagement,
            oidcInteraction: $oidcInteraction,
            baseUrl: $config->getBaseUrl(),
            clientId: $config->getClientId(),
            cache: $cache,
            realmListTtl: $config->getRealmListTtl() ?? self::DEFAULT_REALM_LIST_TTL,
            openIdConfigurationTtl: self::DEFAULT_OPENID_CONFIGURATION_TTL,
            jwkByKidTtl: self::DEFAULT_JWK_BY_KID_TTL,
        );
    }

    public function createForTest(): TestKeycloakHttpClient
    {
        return new TestKeycloakHttpClient();
    }
}
