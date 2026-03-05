<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class DeleteRoleDto
{
    public function __construct(
        private string $realm,
        private string $roleName,
    ) {
        Assert::that($this->realm)->notEmpty();
        Assert::that($this->roleName)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getRoleName(): string
    {
        return $this->roleName;
    }
}
