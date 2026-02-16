<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface LocalKeycloakUserBridgeMapperInterface
{
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto;

    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser
    ): OidcTokenRequestDto;

    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
