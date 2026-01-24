<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;

interface KeycloakHttpClientInterface
{
    public function getUsers(SearchUsersDto $dto): array;

    public function createUser(CreateUserDto $dto): void;

    public function updateUser(string $userId, array $payload): array;

    public function deleteUser(string $userId): void;

    public function createRealm(array $payload): array;

    public function getRoles(): array;

    public function deleteRole(string $role): void;

    public function getJwks(string $realm): array;

    /**
     * @return KeycloakRealm[]
     */
    public function getAvailableRealms(): array;
}
