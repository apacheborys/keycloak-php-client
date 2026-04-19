<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class UpdateUserDto
{
    public function __construct(
        private string $realm,
        private UuidInterface $userId,
        private UpdateUserProfileDto $profile,
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

    public function getProfile(): UpdateUserProfileDto
    {
        return $this->profile;
    }

    /**
     * @return array{
     *     username: string,
     *     email?: string,
     *     emailVerified?: bool,
     *     enabled?: bool,
     *     firstName?: string,
     *     lastName?: string,
     *     attributes?: array<string, list<string>>
     * }
     */
    public function toArray(): array
    {
        return $this->profile->toArray();
    }
}
