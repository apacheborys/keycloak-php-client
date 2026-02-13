<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface LocalKeycloakUserBridgeMapperInterface
{
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto;

    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser
    ): LoginUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
