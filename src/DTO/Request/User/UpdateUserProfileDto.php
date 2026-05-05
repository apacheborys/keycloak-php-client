<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\User;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class UpdateUserProfileDto
{
    /**
     * @var ?list<AttributeValueDto>
     */
    private ?array $attributes;

    /**
     * @param list<AttributeValueDto>|array<string, int|string|UuidInterface|list<string>>|null $attributes
     */
    public function __construct(
        private string $username,
        private ?string $email = null,
        private ?bool $emailVerified = null,
        private ?bool $enabled = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
        ?array $attributes = null,
    ) {
        Assert::that($this->username)->notEmpty();

        if ($this->email !== null) {
            Assert::that($this->email)->notEmpty()->email();
        }

        if ($this->firstName !== null) {
            Assert::that($this->firstName)->notEmpty();
        }

        if ($this->lastName !== null) {
            Assert::that($this->lastName)->notEmpty();
        }

        $this->attributes = $attributes !== null
            ? AttributeValueDto::normalizeCollection(attributes: $attributes)
            : null;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return ?list<AttributeValueDto>
     */
    public function getAttributeDtos(): ?array
    {
        return $this->attributes;
    }

    /**
     * @return ?array<string, list<string>>
     */
    public function getAttributes(): ?array
    {
        if ($this->attributes === null) {
            return null;
        }

        return AttributeValueDto::normalizeCollectionToMap(attributes: $this->attributes);
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
        $result = [
            'username' => $this->username,
        ];

        if ($this->email !== null) {
            $result['email'] = $this->email;
        }

        if ($this->emailVerified !== null) {
            $result['emailVerified'] = $this->emailVerified;
        }

        if ($this->enabled !== null) {
            $result['enabled'] = $this->enabled;
        }

        if ($this->firstName !== null) {
            $result['firstName'] = $this->firstName;
        }

        if ($this->lastName !== null) {
            $result['lastName'] = $this->lastName;
        }

        if ($this->attributes !== null) {
            $attributes = $this->getAttributes();
            Assert::that($attributes)->isArray();

            /** @var array<string, list<string>> $attributes */
            $result['attributes'] = $attributes;
        }

        return $result;
    }
}
