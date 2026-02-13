<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class LoginUserDto
{
    public function __construct(
        private string $realm,
        private string $clientId,
        private string $clientSecret,
        private string $username,
        private string $password,
        private string $grantType = 'password',
    ) {
        Assert::that($this->realm)->notEmpty();
        Assert::that($this->clientId)->notEmpty();
        Assert::that($this->clientSecret)->notEmpty();
        Assert::that($this->username)->notEmpty();
        Assert::that($this->password)->notEmpty();
        Assert::that($this->grantType)->notEmpty()->eq('password');
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    /**
     * @return array<string, string>
     */
    public function toFormParams(): array
    {
        return [
            'grant_type' => $this->grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
