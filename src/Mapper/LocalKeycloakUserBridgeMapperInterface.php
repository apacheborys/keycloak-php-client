<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;

interface LocalKeycloakUserBridgeMapperInterface
{
    /**
     * @param list<KeycloakCredential> $credentials
     */
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $credentials
    ): CreateUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
