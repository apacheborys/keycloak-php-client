<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class UpdateClientScopeDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $clientScopeId,
        private ClientScopeDto $clientScope,
        private ?ClientScopeRealmAssignmentType $realmAssignmentType = null,
    ) {
        Assert::that($this->realm)->notEmpty();

        $payloadClientScopeId = $this->clientScope->getId();
        if ($payloadClientScopeId === null) {
            return;
        }

        Assert::that($payloadClientScopeId->toString())->same($this->clientScopeId->toString());
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getClientScopeId(): UuidInterface
    {
        return $this->clientScopeId;
    }

    public function getClientScope(): ClientScopeDto
    {
        return $this->clientScope;
    }

    public function getRealmAssignmentType(): ?ClientScopeRealmAssignmentType
    {
        return $this->realmAssignmentType;
    }
}
