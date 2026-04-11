<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Assert\Assert;

readonly final class CreateUserProfileAttributeDto
{
    public function __construct(
        private string $realm,
        private AttributeDto $attribute,
    ) {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getAttribute(): AttributeDto
    {
        return $this->attribute;
    }
}
