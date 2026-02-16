<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;

interface KeycloakHttpClientInterface
{
    public function getUsers(SearchUsersDto $dto): array;

    public function createUser(CreateUserDto $dto): void;

    public function updateUser(string $userId, array $payload): array;

    public function deleteUser(DeleteUserDto $dto): void;

    public function createRealm(array $payload): array;

    public function getRoles(): array;

    public function deleteRole(string $role): void;

    public function getJwks(string $realm): array;

    /**
     * @return KeycloakRealm[]
     */
    public function getAvailableRealms(): array;

    public function resetPassword(ResetUserPasswordDto $dto): void;

    public function loginUser(LoginUserDto $dto): RequestAccessDto;
}
