<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
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
        private ?string $scope = null,
        private OidcGrantType $grantType = OidcGrantType::PASSWORD,
    ) {
        Assert::that($this->realm)->notEmpty();
        Assert::that($this->clientId)->notEmpty();
        Assert::that($this->clientSecret)->notEmpty();
        if ($this->scope !== null) {
            Assert::that($this->scope)->notEmpty();
        }
        $this->validateByGrantType();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getGrantType(): OidcGrantType
    {
        return $this->grantType;
    }

    public function getScope(): ?string
    {
        return $this->scope;
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

        if ($this->scope !== null) {
            $result['scope'] = $this->scope;
        }

        if ($this->grantType === OidcGrantType::PASSWORD) {
            $result['username'] = (string) $this->username;
            $result['password'] = (string) $this->password;
        }

        if ($this->grantType === OidcGrantType::REFRESH_TOKEN) {
            $result['refresh_token'] = (string) $this->refreshToken;
        }

        return $result;
    }

    private function validateByGrantType(): void
    {
        if ($this->grantType === OidcGrantType::PASSWORD) {
            Assert::that($this->username)->notEmpty();
            Assert::that($this->password)->notEmpty();
            return;
        }

        Assert::that($this->refreshToken)->notEmpty();
    }
}
