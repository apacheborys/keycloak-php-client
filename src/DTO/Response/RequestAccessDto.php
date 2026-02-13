<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response;

use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Assert\Assert;

final readonly class RequestAccessDto
{
    public function __construct(
        private JsonWebToken $accessToken,
        private int $expiresIn,
        private int $refreshExpiresIn,
        private string $tokenType,
        private int $nonBeforePolicy,
        private string $scope,
    ) {
    }

    public function getAccessToken(): JsonWebToken
    {
        return $this->accessToken;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getRefreshExpiresIn(): int
    {
        return $this->refreshExpiresIn;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getNonBeforePolicy(): int
    {
        return $this->nonBeforePolicy;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('access_token');
        Assert::that($data['access_token'])->string()->notBlank();

        $accessToken = JsonWebToken::fromRawToken($data['access_token']);

        Assert::that($data)->keyExists('expires_in');
        Assert::that($data['expires_in'])->integer()->greaterOrEqualThan(0);

        Assert::that($data)->keyExists('refresh_expires_in');
        Assert::that($data['refresh_expires_in'])->integer()->greaterOrEqualThan(0);

        Assert::that($data)->keyExists('token_type');
        Assert::that($data['token_type'])->string()->eq('Bearer');

        Assert::that($data)->keyExists('not-before-policy');
        Assert::that($data['not-before-policy'])->integer()->greaterOrEqualThan(0);

        Assert::that($data)->keyExists('scope');
        Assert::that($data['scope'])->string();

        return new self(
            accessToken: $accessToken,
            expiresIn: $data['expires_in'],
            refreshExpiresIn: $data['refresh_expires_in'],
            tokenType: $data['token_type'],
            nonBeforePolicy: $data['not-before-policy'],
            scope: $data['scope'],
        );
    }
}
