<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class CreateClientScopeProtocolMapperDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $clientScopeId,
        private ClientScopesProtocolMapperDto $protocolMapper,
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

    public function getProtocolMapper(): ClientScopesProtocolMapperDto
    {
        return $this->protocolMapper;
    }
}
