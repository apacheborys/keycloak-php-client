<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

interface KeycloakHttpClientInterface
{
    public function createUser(array $payload): array;

    public function updateUser(string $userId, array $payload): array;

    public function deleteUser(string $userId): void;

    public function createRealm(array $payload): array;

    public function getRoles(): array;

    public function deleteRole(string $role): void;

    public function getJwks(string $realm): array;
}
