<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface KeycloakServiceInterface
{
    public function createUser(KeycloakUserInterface $localUser, PasswordDto $passwordDto): KeycloakUser;

    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser;

    public function deleteUser(KeycloakUserInterface $user): void;

    /**
     * @return KeycloakRealm[]
     */
    public function getAvailableRealms(): array;

    public function verifyJwt(string $jwt): bool;

    public function loginUser(KeycloakUserInterface $user, string $plainPassword): OidcTokenResponseDto;

    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto;
}
