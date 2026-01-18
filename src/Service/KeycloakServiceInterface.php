<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface KeycloakServiceInterface
{
    public function createUser(KeycloakUserInterface $localUser): array;

    public function updateUser(string $userId, array $payload): array;

    public function deleteUser(string $userId): void;

    public function authenticateJwt(string $jwt, string $realm): bool;
}
