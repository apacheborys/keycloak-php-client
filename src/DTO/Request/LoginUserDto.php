<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakGrantType;
use Assert\Assert;

readonly final class LoginUserDto
{
    public function __construct(
        private string $realm,
        private string $clientId,
        private string $clientSecret,
        private ?string $username = null,
        private ?string $password = null,
        private ?string $refreshToken = null,
        private KeycloakGrantType $grantType = KeycloakGrantType::PASSWORD,
    ) {
        Assert::that($this->realm)->notEmpty();
        Assert::that($this->clientId)->notEmpty();
        Assert::that($this->clientSecret)->notEmpty();
        $this->validateByGrantType();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getGrantType(): KeycloakGrantType
    {
        return $this->grantType;
    }

    /**
     * @return array<string, string>
     */
    public function toFormParams(): array
    {
        $result = [
            'grant_type' => $this->grantType->value,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        if ($this->grantType === KeycloakGrantType::PASSWORD) {
            $result['username'] = (string) $this->username;
            $result['password'] = (string) $this->password;
        }

        if ($this->grantType === KeycloakGrantType::REFRESH_TOKEN) {
            $result['refresh_token'] = (string) $this->refreshToken;
        }

        return $result;
    }

    private function validateByGrantType(): void
    {
        if ($this->grantType === KeycloakGrantType::PASSWORD) {
            Assert::that($this->username)->notEmpty();
            Assert::that($this->password)->notEmpty();
            return;
        }

        Assert::that($this->refreshToken)->notEmpty();
    }
}
