<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;

interface LocalKeycloakUserBridgeMapperInterface
{
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        /** @var KeycloakCredential[] $credentials */
        array $credentials
    ): CreateUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
