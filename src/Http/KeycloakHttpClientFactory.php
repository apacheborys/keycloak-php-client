<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Psr\Http\Client\ClientInterface;

final class KeycloakHttpClientFactory
{
    public function create(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        ClientInterface|null $httpClient = null
    ): KeycloakHttpClientInterface {
        return new KeycloakHttpClient(
            baseUrl: $baseUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            httpClient: $httpClient
        );
    }
}
