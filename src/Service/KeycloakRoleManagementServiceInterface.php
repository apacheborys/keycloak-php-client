<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface KeycloakRoleManagementServiceInterface
{
    public function synchronizeRolesOnUserCreation(KeycloakUserInterface $localUser, KeycloakUser $createdUser): void;

    public function synchronizeRolesOnUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): void;
}
