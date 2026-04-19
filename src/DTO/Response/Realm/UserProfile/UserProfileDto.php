<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile;

use Assert\Assert;

final readonly class UserProfileDto
{
    /**
     * @param list<AttributeDto> $attributes
     * @param list<UserProfileGroupDto> $groups
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private array $attributes = [],
        private array $groups = [],
        private array $extra = [],
    ) {
        foreach ($this->attributes as $attribute) {
            Assert::that($attribute)->isInstanceOf(AttributeDto::class);
        }

        foreach ($this->groups as $group) {
            Assert::that($group)->isInstanceOf(UserProfileGroupDto::class);
        }

        foreach ($this->extra as $key => $value) {
            Assert::that($key)->string()->notBlank();
            $_ = $value;
        }
    }

    /**
     * @return list<AttributeDto>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<UserProfileGroupDto>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function hasAttribute(string $attributeName): bool
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->getName() === $attributeName) {
                return true;
            }
        }

        return false;
    }

    public function withAppendedAttribute(AttributeDto $attribute): self
    {
        $attributes = $this->attributes;
        $attributes[] = $attribute;

        return new self(
            attributes: $attributes,
            groups: $this->groups,
            extra: $this->extra,
        );
    }

    public function withUpdatedAttribute(AttributeDto $attribute): self
    {
        $attributes = [];
        $updated = false;

        foreach ($this->attributes as $item) {
            if ($item->getName() === $attribute->getName()) {
                $attributes[] = $attribute->withPreservedUnknownFieldsFrom(attribute: $item);
                $updated = true;
                continue;
            }

            $attributes[] = $item;
        }

        if (!$updated) {
            throw new \RuntimeException(sprintf('User profile attribute "%s" was not found.', $attribute->getName()));
        }

        return new self(
            attributes: $attributes,
            groups: $this->groups,
            extra: $this->extra,
        );
    }

    public function withoutAttribute(string $attributeName): self
    {
        $attributes = [];
        $deleted = false;

        foreach ($this->attributes as $attribute) {
            if ($attribute->getName() === $attributeName) {
                $deleted = true;
                continue;
            }

            $attributes[] = $attribute;
        }

        if (!$deleted) {
            throw new \RuntimeException(sprintf('User profile attribute "%s" was not found.', $attributeName));
        }

        return new self(
            attributes: $attributes,
            groups: $this->groups,
            extra: $this->extra,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->extra;
        $data['attributes'] = array_map(
            static fn (AttributeDto $attribute): array => $attribute->toArray(),
            $this->attributes,
        );
        $data['groups'] = array_map(
            static fn (UserProfileGroupDto $group): array => $group->toArray(),
            $this->groups,
        );

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var mixed $rawAttributes */
        $rawAttributes = $data['attributes'] ?? [];
        Assert::that($rawAttributes)->isArray();
        /** @var array<int, mixed> $rawAttributes */
        $attributes = [];
        foreach ($rawAttributes as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $attributes[] = AttributeDto::fromArray($item);
        }

        /** @var mixed $rawGroups */
        $rawGroups = $data['groups'] ?? [];
        Assert::that($rawGroups)->isArray();
        /** @var array<int, mixed> $rawGroups */
        $groups = [];
        foreach ($rawGroups as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $groups[] = UserProfileGroupDto::fromArray($item);
        }

        return new self(
            attributes: $attributes,
            groups: $groups,
            extra: self::extractExtra(data: $data),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractExtra(array $data): array
    {
        unset(
            $data['attributes'],
            $data['groups'],
        );

        return $data;
    }
}
