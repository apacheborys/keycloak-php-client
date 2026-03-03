<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;
use Ramsey\Uuid\Uuid;

readonly final class UpdateUserDto
{
    public function __construct(
        private string $realm,
        private string $userId,
        private UpdateUserProfileDto $profile,
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

    public function getProfile(): UpdateUserProfileDto
    {
        return $this->profile;
    }

    /**
     * @return array{
     *     username: string,
     *     email: string,
     *     emailVerified?: bool,
     *     enabled?: bool,
     *     firstName?: string,
     *     lastName?: string
     * }
     */
    public function toArray(): array
    {
        return $this->profile->toArray();
    }
}
