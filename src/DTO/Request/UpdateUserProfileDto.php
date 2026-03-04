<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class UpdateUserProfileDto
{
    public function __construct(
        private string $username,
        private ?string $email = null,
        private ?bool $emailVerified = null,
        private ?bool $enabled = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
    ) {
        Assert::that($this->username)->notEmpty();

        if ($this->email !== null) {
            Assert::that($this->email)->notEmpty()->email();
        }

        if ($this->firstName !== null) {
            Assert::that($this->firstName)->notEmpty();
        }

        if ($this->lastName !== null) {
            Assert::that($this->lastName)->notEmpty();
        }
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return array{
     *     username: string,
     *     email?: string,
     *     emailVerified?: bool,
     *     enabled?: bool,
     *     firstName?: string,
     *     lastName?: string
     * }
     */
    public function toArray(): array
    {
        $result = [
            'username' => $this->username,
        ];

        if ($this->email !== null) {
            $result['email'] = $this->email;
        }

        if ($this->emailVerified !== null) {
            $result['emailVerified'] = $this->emailVerified;
        }

        if ($this->enabled !== null) {
            $result['enabled'] = $this->enabled;
        }

        if ($this->firstName !== null) {
            $result['firstName'] = $this->firstName;
        }

        if ($this->lastName !== null) {
            $result['lastName'] = $this->lastName;
        }

        return $result;
    }
}
