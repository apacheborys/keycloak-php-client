<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class KeycloakHttpClientFactory
{
    public function create(
        string $baseUrl,
        string $clientRealm,
        string $clientId,
        string $clientSecret,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        CacheItemPoolInterface|null $cache = null
    ): KeycloakHttpClientInterface {
        return new KeycloakHttpClient(
            baseUrl: $baseUrl,
            clientRealm: $clientRealm,
            clientId: $clientId,
            clientSecret: $clientSecret,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            cache: $cache
        );
    }
}
