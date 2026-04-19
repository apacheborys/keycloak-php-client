<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class DeleteClientScopeProtocolMapperDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $clientScopeId,
        private UuidInterface $protocolMapperId,
    ) {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getClientScopeId(): UuidInterface
    {
        return $this->clientScopeId;
    }

    public function getProtocolMapperId(): UuidInterface
    {
        return $this->protocolMapperId;
    }
}
