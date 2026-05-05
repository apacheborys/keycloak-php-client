<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile;

use Assert\Assert;

readonly final class DeleteUserProfileAttributeDto
{
    public function __construct(
        private string $realm,
        private string $attributeName,
    ) {
        Assert::that($this->realm)->notEmpty();
        Assert::that($this->attributeName)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getAttributeName(): string
    {
        return $this->attributeName;
    }
}
