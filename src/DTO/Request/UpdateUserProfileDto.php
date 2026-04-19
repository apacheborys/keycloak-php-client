<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\Assert;

readonly final class UpdateUserProfileDto
{
    /**
     * @param ?list<RoleDto> $roles
     * @param ?array<string, string|list<string>> $attributes
     */
    public function __construct(
        private string $username,
        private ?string $email = null,
        private ?bool $emailVerified = null,
        private ?bool $enabled = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
        private ?array $roles = null,
        private ?array $attributes = null,
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

        if ($this->roles !== null) {
            foreach ($this->roles as $role) {
                Assert::that($role)->isInstanceOf(RoleDto::class);
            }
        }

        if ($this->attributes !== null) {
            self::assertAttributesMap(attributes: $this->attributes);
        }
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
     * @return ?array<string, list<string>>
     */
    public function getAttributes(): ?array
    {
        if ($this->attributes === null) {
            return null;
        }

        return self::normalizeAttributesMap(attributes: $this->attributes);
    }

    /**
     * @return ?list<RoleDto>
     */
    public function getRoles(): ?array
    {
        return $this->roles;
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

    /**
     * @param array<string, string|list<string>> $attributes
     */
    private static function assertAttributesMap(array $attributes): void
    {
        foreach ($attributes as $attributeName => $attributeValue) {
            Assert::that($attributeName)->string()->notEmpty();

            if (is_string($attributeValue)) {
                continue;
            }

            Assert::that($attributeValue)->isArray();
            foreach ($attributeValue as $singleValue) {
                Assert::that($singleValue)->string();
            }
        }
    }

    /**
     * @param array<string, string|list<string>> $attributes
     * @return array<string, list<string>>
     */
    private static function normalizeAttributesMap(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $attributeName => $attributeValue) {
            if (is_string($attributeValue)) {
                $normalized[$attributeName] = [$attributeValue];
                continue;
            }

            /** @var list<string> $attributeValue */
            $normalized[$attributeName] = array_values($attributeValue);
        }

        return $normalized;
    }
}
