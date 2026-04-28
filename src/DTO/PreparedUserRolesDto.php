<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO;

use Assert\Assert;

readonly final class PreparedUserRolesDto
{
    /**
     * @param list<RoleDto> $roles
     */
    public function __construct(
        private array $roles = [],
        private bool $roleCreationAllowed = false,
    ) {
        foreach ($this->roles as $role) {
            Assert::that($role)->isInstanceOf(RoleDto::class);
        }
    }

    /**
     * @return list<RoleDto>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isRoleCreationAllowed(): bool
    {
        return $this->roleCreationAllowed;
    }
}
