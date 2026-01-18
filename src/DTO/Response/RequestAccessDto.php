<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response;

use Assert\Assert;

final readonly class RequestAccessDto
{
    private string $accessToken;

    private int $expiresIn;

    private int $refreshExpiresIn;

    private string $tokenType;

    private int $nonBeforePolicy;

    private string $scope;

    public function getAccessToken(): string
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
        $dto = new self();

        Assert::that(value: $data)->keyExists(key: 'access_token');
        Assert::that(value: $data['access_token'])->string()->notBlank();
        $dto->accessToken = $data['access_token'];

        Assert::that(value: $data)->keyExists(key: 'expires_in');
        Assert::that(value: $data['expires_in'])->integer()->greaterOrEqualThan(limit: 0);
        $dto->expiresIn = $data['expires_in'];

        Assert::that(value: $data)->keyExists(key: 'refresh_expires_in');
        Assert::that(value: $data['refresh_expires_in'])->integer()->greaterOrEqualThan(limit: 0);
        $dto->refreshExpiresIn = $data['refresh_expires_in'];

        Assert::that(value: $data)->keyExists(key: 'token_type');
        Assert::that(value: $data['token_type'])->string()->eq(value2: 'Bearer');
        $dto->tokenType = $data['token_type'];

        Assert::that(value: $data)->keyExists(key: 'not-before-policy');
        Assert::that(value: $data['not-before-policy'])->integer()->greaterOrEqualThan(limit: 0);
        $dto->nonBeforePolicy = $data['not-before-policy'];

        Assert::that(value: $data)->keyExists(key: 'scope');
        Assert::that(value: $data['scope'])->string();
        $dto->scope = $data['scope'];

        return $dto;
    }
}
