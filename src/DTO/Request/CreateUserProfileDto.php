<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class CreateUserProfileDto
{
    public function __construct(
        private string $username,
        private string $email,
        private bool $emailVerified,
        private bool $enabled,
        private string $firstName,
        private string $lastName,
        private string $realm,
    ) {
        Assert::that(value: $username)->notEmpty();
        Assert::that(value: $email)->notEmpty()->email();
        Assert::that(value: $firstName)->notEmpty();
        Assert::that(value: $lastName)->notEmpty();
        Assert::that(value: $realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'email' => $this->email,
            'emailVerified' => $this->emailVerified,
            'enabled' => $this->enabled,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
        ];
    }
}
