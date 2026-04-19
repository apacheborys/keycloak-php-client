<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class GetClientScopesDto
{
    public function __construct(private string $realm)
    {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }
}
