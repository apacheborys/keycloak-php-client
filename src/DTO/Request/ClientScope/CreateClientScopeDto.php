<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use Assert\Assert;

readonly final class CreateClientScopeDto
{
    public function __construct(
        private string $realm,
        private ClientScopeDto $clientScope,
        private ?ClientScopeRealmAssignmentType $realmAssignmentType = null,
    ) {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
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
