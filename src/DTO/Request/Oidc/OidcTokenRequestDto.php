<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\Oidc;

use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use Assert\Assert;
use Assert\InvalidArgumentException;

readonly final class OidcTokenRequestDto
{
    private const string REDACTED = '[redacted]';

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

    public static function forClientCredentials(
        string $realm,
        string $clientId,
        string $clientSecret,
        ?string $scope = null,
    ): self {
        return new self(
            realm: $realm,
            clientId: $clientId,
            clientSecret: $clientSecret,
            scope: $scope,
            grantType: OidcGrantType::CLIENT_CREDENTIALS,
        );
    }

    public static function forPasswordGrant(
        string $realm,
        string $clientId,
        string $clientSecret,
        string $username,
        string $password,
        ?string $scope = null,
    ): self {
        return new self(
            realm: $realm,
            clientId: $clientId,
            clientSecret: $clientSecret,
            username: $username,
            password: $password,
            scope: $scope,
            grantType: OidcGrantType::PASSWORD,
        );
    }

    public static function forRefreshToken(
        string $realm,
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        ?string $scope = null,
    ): self {
        return new self(
            realm: $realm,
            clientId: $clientId,
            clientSecret: $clientSecret,
            refreshToken: $refreshToken,
            scope: $scope,
            grantType: OidcGrantType::REFRESH_TOKEN,
        );
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
     * @return array<string, OidcGrantType|string|null>
     */
    public function __debugInfo(): array
    {
        return [
            'realm' => $this->realm,
            'clientId' => $this->clientId,
            'clientSecret' => self::REDACTED,
            'username' => $this->username,
            'password' => $this->password !== null ? self::REDACTED : null,
            'refreshToken' => $this->refreshToken !== null ? self::REDACTED : null,
            'scope' => $this->scope,
            'grantType' => $this->grantType,
        ];
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

        return match ($this->grantType) {
            OidcGrantType::CLIENT_CREDENTIALS => $result,
            OidcGrantType::PASSWORD => $result + [
                'username' => (string) $this->username,
                'password' => (string) $this->password,
            ],
            OidcGrantType::REFRESH_TOKEN => $result + [
                'refresh_token' => (string) $this->refreshToken,
            ],
        };
    }

    private function validateByGrantType(): void
    {
        match ($this->grantType) {
            OidcGrantType::CLIENT_CREDENTIALS => $this->validateClientCredentialsGrant(),
            OidcGrantType::PASSWORD => $this->validatePasswordGrant(),
            OidcGrantType::REFRESH_TOKEN => $this->validateRefreshTokenGrant(),
        };
    }

    private function validateClientCredentialsGrant(): void
    {
        if ($this->username !== null || $this->password !== null || $this->refreshToken !== null) {
            throw new InvalidArgumentException(
                'OIDC client credentials grant must not include username, password, or refresh token.',
                0,
            );
        }
    }

    private function validatePasswordGrant(): void
    {
        Assert::that($this->username, 'OIDC password grant requires a username.')->notEmpty();
        Assert::that($this->password, 'OIDC password grant requires a password.')->notEmpty();

        if ($this->refreshToken !== null) {
            throw new InvalidArgumentException(
                'OIDC password grant must not include a refresh token.',
                0,
            );
        }
    }

    private function validateRefreshTokenGrant(): void
    {
        Assert::that(
            $this->refreshToken,
            'OIDC refresh token grant requires a refresh token.',
        )->notEmpty();

        if ($this->username !== null || $this->password !== null) {
            throw new InvalidArgumentException(
                'OIDC refresh token grant must not include username or password.',
                0,
            );
        }
    }
}
