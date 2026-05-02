<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class DeleteUserDto
{
    /**
     * @param int|string|UuidInterface|null $localUserId Local application user id, kept out of the Keycloak request.
     */
    public function __construct(
        private string $realm,
        private ?UuidInterface $userId = null,
        private int|string|UuidInterface|null $localUserId = null,
    ) {
        Assert::that($this->realm)->notEmpty();

        if (is_string($this->localUserId)) {
            Assert::that($this->localUserId)->notEmpty();
        }
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getUserId(): ?UuidInterface
    {
        return $this->userId;
    }

    public function getLocalUserId(): int|string|UuidInterface|null
    {
        return $this->localUserId;
    }
}
