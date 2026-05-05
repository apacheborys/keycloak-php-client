<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\Role;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\Assert;

readonly final class CreateRoleDto
{
    public function __construct(
        private string $realm,
        private RoleDto $role,
    ) {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getRole(): RoleDto
    {
        return $this->role;
    }

    /**
     * @return array{
     *     name: string,
     *     description?: string,
     *     composite: bool,
     *     clientRole: bool
     * }
     */
    public function toArray(): array
    {
        return $this->role->toCreatePayload();
    }
}
