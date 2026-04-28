<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\PreparedUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface LocalKeycloakUserBridgeMapperInterface
{
    public function getRealm(KeycloakUserInterface $localUser): string;

    /**
     * Compiles local user roles into Keycloak realm roles.
     *
     * Role naming rules such as application-specific prefixes or suffixes belong here.
     *
     * @param list<RoleDto> $availableRoles
     */
    public function prepareRolesForUser(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): PreparedUserRolesDto;

    /**
     * @param list<RoleDto> $availableRoles
     */
    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): CreateUserProfileDto;

    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword
    ): OidcTokenRequestDto;

    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto;

    /**
     * @param list<RoleDto> $availableRoles
     */
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UpdateUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
