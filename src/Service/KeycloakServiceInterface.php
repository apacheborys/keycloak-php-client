<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface KeycloakServiceInterface
{
    public function createUser(KeycloakUserInterface $localUser, PasswordDto $passwordDto): KeycloakUser;

    public function updateUser(string $userId, array $payload): array;

    public function deleteUser(DeleteUserDto $dto): void;

    /**
     * @return KeycloakRealm[]
     */
    public function getAvailableRealms(): array;

    public function authenticateJwt(string $jwt, string $realm): bool;

    public function loginUser(KeycloakUserInterface $user): RequestAccessDto;

    public function refreshToken(LoginUserDto $dto): RequestAccessDto;
}
