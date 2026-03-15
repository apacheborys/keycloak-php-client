<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;

interface UserManagementHttpClientInterface
{
    /**
     * @return list<KeycloakUser>
     */
    public function getUsers(SearchUsersDto $dto): array;

    public function createUser(CreateUserDto $dto): void;

    public function updateUser(UpdateUserDto $dto): void;

    public function deleteUser(DeleteUserDto $dto): void;

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    public function createRealm(array $payload): array;

    public function resetPassword(ResetUserPasswordDto $dto): void;
}
