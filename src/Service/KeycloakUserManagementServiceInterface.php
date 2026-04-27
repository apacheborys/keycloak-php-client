<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Ramsey\Uuid\UuidInterface;

interface KeycloakUserManagementServiceInterface
{
    public function createUser(KeycloakUserInterface $localUser, PasswordDto $passwordDto): KeycloakUser;

    /**
     * @return list<KeycloakUser>
     */
    public function searchUsers(SearchUsersDto $dto): array;

    public function findUser(KeycloakUserInterface $localUser): KeycloakUser;

    public function findUserById(string $realm, UuidInterface $userId): KeycloakUser;

    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser;

    public function deleteUser(KeycloakUserInterface $user): void;
}
