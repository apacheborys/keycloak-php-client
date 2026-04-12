<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class DeleteClientScopeDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $clientScopeId,
        private bool $removeFromRealmDefaultAssignmentsBeforeDelete = true,
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

    public function shouldRemoveFromRealmDefaultAssignmentsBeforeDelete(): bool
    {
        return $this->removeFromRealmDefaultAssignmentsBeforeDelete;
    }
}
