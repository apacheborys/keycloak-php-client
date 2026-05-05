<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\User;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class GetUserByIdDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $userId,
    ) {
        Assert::that($this->realm)->notEmpty();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getUserId(): UuidInterface
    {
        return $this->userId;
    }
}
