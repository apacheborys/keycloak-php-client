<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface LocalKeycloakUserBridgeMapperInterface
{
    public function prepareLocalUserForKeycloakUserCreation(KeycloakUserInterface $localUser): CreateUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
