<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;

interface UserManagementHttpClientInterface
{
    /**
     * @return list<KeycloakUser>
     */
    public function getUsers(SearchUsersDto $dto): array;

    public function getUserById(GetUserByIdDto $dto): KeycloakUser;

    /**
     * Supports custom user attributes through CreateUserDto profile payload.
     */
    public function createUser(CreateUserDto $dto): void;

    /**
     * Supports full user attribute updates through UpdateUserDto profile payload.
     * HTTP calls require UpdateUserDto::getUserId() to be non-null; the service layer
     * resolves it for mapper-generated DTOs before calling transport.
     */
    public function updateUser(UpdateUserDto $dto): void;

    /**
     * HTTP calls require DeleteUserDto::getUserId() to be non-null; the service layer
     * resolves it for mapper-generated DTOs before calling transport.
     */
    public function deleteUser(DeleteUserDto $dto): void;

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    public function createRealm(array $payload): array;

    public function resetPassword(ResetUserPasswordDto $dto): void;
}
