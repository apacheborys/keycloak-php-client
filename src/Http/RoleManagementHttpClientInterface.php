<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;

interface RoleManagementHttpClientInterface
{
    /**
     * @return list<RoleDto>
     */
    public function getRoles(GetRolesDto $dto): array;

    /**
     * @return list<RoleDto>
     */
    public function getAvailableUserRoles(GetUserAvailableRolesDto $dto): array;

    public function createRole(CreateRoleDto $dto): void;

    public function deleteRole(DeleteRoleDto $dto): void;

    public function assignRolesToUser(AssignUserRolesDto $dto): void;

    public function unassignRolesFromUser(AssignUserRolesDto $dto): void;
}
