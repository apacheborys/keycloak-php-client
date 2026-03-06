<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;

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
