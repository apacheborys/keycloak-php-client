<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

use Assert\Assert;

final readonly class KeycloakClientConfig
{
    public function __construct(
        private string $baseUrl,
        private string $clientRealm,
        private string $clientId,
        private string $clientSecret,
        private ?int $realmListTtl = null,
    ) {
        Assert::that(value: $this->baseUrl)->notEmpty()->url();
        Assert::that(value: $this->clientRealm)->notEmpty();
        Assert::that(value: $this->clientId)->notEmpty();
        Assert::that(value: $this->clientSecret)->notEmpty();

        if ($this->realmListTtl !== null) {
            Assert::that(value: $this->realmListTtl)->integer()->greaterOrEqualThan(limit: 0);
        }
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getClientRealm(): string
    {
        return $this->clientRealm;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getRealmListTtl(): ?int
    {
        return $this->realmListTtl;
    }
}
