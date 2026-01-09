<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

final class KeycloakHttpClientFactory
{
    public function create(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        string $username,
        string $password,
        object|null $httpClient = null
    ): KeycloakHttpClientInterface {
        return new KeycloakHttpClient(
            baseUrl: $baseUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            username: $username,
            password: $password,
            httpClient: $httpClient
        );
    }
}
