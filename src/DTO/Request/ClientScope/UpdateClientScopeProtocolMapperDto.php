<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class UpdateClientScopeProtocolMapperDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $clientScopeId,
        private UuidInterface $protocolMapperId,
        private ClientScopesProtocolMapperDto $protocolMapper,
    ) {
        Assert::that($this->realm)->notEmpty();

        $payloadMapperId = $this->protocolMapper->getId();
        if ($payloadMapperId === null) {
            return;
        }

        Assert::that($payloadMapperId->toString())->same($this->protocolMapperId->toString());
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

    public function getProtocolMapper(): ClientScopesProtocolMapperDto
    {
        return $this->protocolMapper;
    }
}
