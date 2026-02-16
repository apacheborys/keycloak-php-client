<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;
use Ramsey\Uuid\Uuid;

readonly final class DeleteUserDto
{
    public function __construct(
        private string $realm,
        private string $userId,
    ) {
        Assert::that($this->realm)->notEmpty();
        Assert::that($this->userId)->notEmpty();
        Assert::that(Uuid::isValid($this->userId))->true();
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
