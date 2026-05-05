<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile;

use Assert\Assert;

readonly final class GetUserProfileDto
{
    public function __construct(
        private string $realm,
    ) {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }
}
