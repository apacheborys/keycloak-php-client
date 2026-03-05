<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\Assert;

readonly final class CreateUserProfileDto
{
    /**
     * @param list<RoleDto> $roles
     */
    public function __construct(
        private string $username,
        private string $email,
        private bool $emailVerified,
        private bool $enabled,
        private string $firstName,
        private string $lastName,
        private string $realm,
        private array $roles = [],
    ) {
        Assert::that($username)->notEmpty();
        Assert::that($email)->notEmpty()->email();
        Assert::that($firstName)->notEmpty();
        Assert::that($lastName)->notEmpty();
        Assert::that($realm)->notEmpty();

        foreach ($this->roles as $role) {
            Assert::that($role)->isInstanceOf(RoleDto::class);
        }
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return list<RoleDto>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return array{
     *     username: string,
     *     email: string,
     *     emailVerified: bool,
     *     enabled: bool,
     *     firstName: string,
     *     lastName: string
     * }
     */
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
