<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class KeycloakHttpClientFactory
{
    public function create(
        KeycloakClientConfig $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?CacheItemPoolInterface $cache = null
    ): KeycloakHttpClientInterface {
        return new KeycloakHttpClient(
            baseUrl: $config->getBaseUrl(),
            clientRealm: $config->getClientRealm(),
            clientId: $config->getClientId(),
            clientSecret: $config->getClientSecret(),
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            cache: $cache,
            realmListTtl: $config->getRealmListTtl() ?? KeycloakHttpClient::REALM_LIST_TTL,
        );
    }

    public function createForTest(): TestKeycloakHttpClient
    {
        return new TestKeycloakHttpClient();
    }
}
