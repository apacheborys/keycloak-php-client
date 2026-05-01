<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Mapper;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface LocalKeycloakUserBridgeMapperInterface
{
    public const string DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME = 'external-user-id';

    public function getRealm(KeycloakUserInterface $localUser): string;

    /**
     * Returns the Keycloak user attribute that stores KeycloakUserInterface::getId().
     */
    public function getLocalUserIdAttributeName(KeycloakUserInterface $localUser): string;

    /**
     * Builds the Keycloak user creation profile from a local user.
     *
     * Returned roles are treated as final Keycloak realm role names. Apply application-specific
     * role prefixes or suffixes before returning the DTO. Return an empty role list to skip
     * role synchronization for this user.
     *
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

    /**
     * The returned DTO must carry the local user id from KeycloakUserInterface::getId().
     * The Keycloak user id is optional here because the service layer resolves the target
     * user by Keycloak id first and then by this mapper's local-id attribute fallback.
     */
    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto;

    /**
     * Builds the Keycloak user update profile from old and new local user versions.
     * The returned DTO must carry the local user id from KeycloakUserInterface::getId().
     * The Keycloak user id is optional here because the service layer resolves the target
     * user by Keycloak id first and then by this mapper's local-id attribute fallback.
     *
     * Returned roles are treated as final desired Keycloak realm role names. Apply
     * application-specific role prefixes or suffixes before returning the DTO. Return null
     * or an empty role list to skip role synchronization for this update.
     *
     * @param list<RoleDto> $availableRoles
     */
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UpdateUserDto;

    public function support(KeycloakUserInterface $localUser): bool;
}
