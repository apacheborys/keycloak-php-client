<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Http\Discovery\HttpClientDiscovery;
use LogicException;
use Psr\Http\Client\ClientInterface;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private ClientInterface $httpClient,
    ) {
    }

    public function createUser(array $payload): array
    {
        throw new LogicException(message: 'HTTP createUser is not implemented yet.');
    }

    public function updateUser(string $userId, array $payload): array
    {
        throw new LogicException(message: 'HTTP updateUser is not implemented yet.');
    }

    public function deleteUser(string $userId): void
    {
        throw new LogicException(message: 'HTTP deleteUser is not implemented yet.');
    }

    public function createRealm(array $payload): array
    {
        throw new LogicException(message: 'HTTP createRealm is not implemented yet.');
    }

    public function getRealms(): array
    {
        throw new LogicException('HTTP getRealms is not implemented yet.');
    }

    public function deleteRealm(string $realmId): void
    {
        throw new LogicException('HTTP deleteRealm is not implemented yet.');
    }

    public function getJwks(string $realm): array
    {
        throw new LogicException(message: 'HTTP getJwks is not implemented yet.');
    }
}
