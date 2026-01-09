<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use LogicException;

final class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $username,
        private readonly string $password,
        private readonly object|null $httpClient = null
    ) {
    }

    public function createUser(array $payload): array
    {
        throw new LogicException('HTTP createUser is not implemented yet.');
    }

    public function updateUser(string $userId, array $payload): array
    {
        throw new LogicException('HTTP updateUser is not implemented yet.');
    }

    public function deleteUser(string $userId): void
    {
        throw new LogicException('HTTP deleteUser is not implemented yet.');
    }

    public function createRealm(array $payload): array
    {
        throw new LogicException('HTTP createRealm is not implemented yet.');
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
        throw new LogicException('HTTP getJwks is not implemented yet.');
    }
}
