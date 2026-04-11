<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile;

use Assert\Assert;

final readonly class UserProfileDto
{
    /**
     * @param list<AttributeDto> $attributes
     * @param list<UserProfileGroupDto> $groups
     */
    public function __construct(
        private array $attributes = [],
        private array $groups = [],
    ) {
        foreach ($this->attributes as $attribute) {
            Assert::that($attribute)->isInstanceOf(AttributeDto::class);
        }

        foreach ($this->groups as $group) {
            Assert::that($group)->isInstanceOf(UserProfileGroupDto::class);
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
        );
    }

    public function withUpdatedAttribute(AttributeDto $attribute): self
    {
        $attributes = [];
        $updated = false;

        foreach ($this->attributes as $item) {
            if ($item->getName() === $attribute->getName()) {
                $attributes[] = $attribute;
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
        );
    }

    /**
     * @return array{
     *     attributes: list<array{
     *         name: string,
     *         displayName?: string,
     *         validations: array<string, array<string, mixed>>,
     *         permissions: array{view: list<string>, edit: list<string>},
     *         multivalued: bool,
     *         annotations: array<string, string>
     *     }>,
     *     groups: list<array{
     *         name: string,
     *         displayHeader?: string,
     *         displayDescription?: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'attributes' => array_map(
                static fn (AttributeDto $attribute): array => $attribute->toArray(),
                $this->attributes,
            ),
            'groups' => array_map(
                static fn (UserProfileGroupDto $group): array => $group->toArray(),
                $this->groups,
            ),
        ];
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
        );
    }
}
