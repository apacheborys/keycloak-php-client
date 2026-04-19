<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class EnsureUserIdentifierAttributeDto
{
    public function __construct(
        private string $attributeName,
        private string $displayName,
        private bool $createIfMissing = false,
        private bool $exposeInJwt = false,
        private string $clientScopeName = 'profile',
        private ?string $jwtClaimName = null,
        private ?string $protocolMapperName = null,
    ) {
        Assert::that($this->attributeName)->string()->notBlank();
        Assert::that($this->displayName)->string()->notBlank();
        Assert::that($this->clientScopeName)->string()->notBlank();

        if ($this->jwtClaimName !== null) {
            Assert::that($this->jwtClaimName)->string()->notBlank();
        }

        if ($this->protocolMapperName !== null) {
            Assert::that($this->protocolMapperName)->string()->notBlank();
        }
    }

    public function getAttributeName(): string
    {
        return $this->attributeName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function shouldCreateIfMissing(): bool
    {
        return $this->createIfMissing;
    }

    public function shouldExposeInJwt(): bool
    {
        return $this->exposeInJwt;
    }

    public function getClientScopeName(): string
    {
        return $this->clientScopeName;
    }

    public function getJwtClaimName(): string
    {
        if ($this->jwtClaimName !== null) {
            return $this->jwtClaimName;
        }

        return str_replace('-', '_', $this->attributeName);
    }

    public function getProtocolMapperName(): string
    {
        if ($this->protocolMapperName !== null) {
            return $this->protocolMapperName;
        }

        return $this->displayName . ' attribute';
    }
}
