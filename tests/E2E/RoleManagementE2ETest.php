<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;

/**
 * Realm role CRUD and user role mappings through the HTTP facade.
 */
final class RoleManagementE2ETest extends KeycloakE2ETestCase
{
    public function testRoleCanBeCreatedAssignedUnassignedAndDeleted(): void
    {
        $roleName = $this->uniqueName('e2e-role');
        $this->trackRole($roleName);

        $this->httpClient->createRole(new CreateRoleDto(
            realm: $this->realm,
            role: new RoleDto(name: $roleName, description: 'Created by the E2E suite'),
        ));

        $role = $this->findRoleByName($roleName);
        self::assertNotNull($role, 'Created role must be listed by getRoles().');
        self::assertNotNull($role->getId());
        self::assertSame('Created by the E2E suite', $role->getDescription());
        self::assertFalse($role->isClientRole());

        $localUser = $this->newLocalUser();
        $this->httpClient->createUser(new CreateUserDto(
            profile: $this->mapper->prepareLocalUserForKeycloakUserCreation($localUser),
        ));
        $user = $this->findUserByUsername($localUser->getUsername());
        self::assertNotNull($user);
        $this->trackUser($user->getId());

        $availableDto = new GetUserAvailableRolesDto(realm: $this->realm, userId: $user->getId());
        self::assertContains($roleName, self::roleNames($this->httpClient->getAvailableUserRoles($availableDto)));

        $this->httpClient->assignRolesToUser(new AssignUserRolesDto(realm: $this->realm, userId: $user->getId(), roles: [$role]));
        self::assertNotContains($roleName, self::roleNames($this->httpClient->getAvailableUserRoles($availableDto)));

        $this->httpClient->unassignRolesFromUser(new AssignUserRolesDto(realm: $this->realm, userId: $user->getId(), roles: [$role]));
        self::assertContains($roleName, self::roleNames($this->httpClient->getAvailableUserRoles($availableDto)));

        $this->httpClient->deleteRole(new DeleteRoleDto(realm: $this->realm, roleName: $roleName));
        self::assertNotContains($roleName, self::roleNames($this->httpClient->getRoles(new GetRolesDto(realm: $this->realm))));
    }
}
