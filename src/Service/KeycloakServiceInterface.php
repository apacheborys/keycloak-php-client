<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

interface KeycloakServiceInterface
{
    public function createUser(array $payload): array;

    public function updateUser(string $userId, array $payload): array;

    public function deleteUser(string $userId): void;

    public function authenticateJwt(string $jwt, string $realm): bool;
}
