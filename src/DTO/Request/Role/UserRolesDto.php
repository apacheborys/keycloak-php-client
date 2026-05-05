<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\Role;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\Assert;

readonly final class UserRolesDto
{
    /**
     * @param ?list<RoleDto> $roles
     */
    public function __construct(
        private string $realm,
        private ?array $roles = null,
    ) {
        Assert::that($this->realm)->notEmpty();

        if ($this->roles !== null) {
            foreach ($this->roles as $role) {
                Assert::that($role)->isInstanceOf(RoleDto::class);
            }
        }
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    /**
     * @return ?list<RoleDto>
     */
    public function getRoles(): ?array
    {
        return $this->roles;
    }
}
