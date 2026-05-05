<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\User;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly class AttributeValueDto
{
    /**
     * @param int|string|UuidInterface|list<string> $attributeValue
     */
    public function __construct(
        private string $attributeName,
        private int|string|UuidInterface|array $attributeValue,
    ) {
        Assert::that($this->attributeName)->notEmpty();

        if (is_array($this->attributeValue)) {
            foreach ($this->attributeValue as $singleValue) {
                Assert::that($singleValue)->string();
            }

            return;
        }

        if (is_int($this->attributeValue) || is_string($this->attributeValue)) {
            return;
        }

        Assert::that($this->attributeValue)->isInstanceOf(UuidInterface::class);
    }

    public function getAttributeName(): string
    {
        return $this->attributeName;
    }

    /**
     * @return int|string|UuidInterface|list<string>
     */
    public function getAttributeValue(): int|string|UuidInterface|array
    {
        return $this->attributeValue;
    }

    /**
     * @return list<string>
     */
    public function getNormalizedValues(): array
    {
        if (is_array($this->attributeValue)) {
            return array_values($this->attributeValue);
        }

        if ($this->attributeValue instanceof UuidInterface) {
            return [$this->attributeValue->toString()];
        }

        return [(string) $this->attributeValue];
    }

    /**
     * @param list<self>|array<string, int|string|UuidInterface|list<string>> $attributes
     * @return list<self>
     */
    public static function normalizeCollection(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $attributeName => $attributeValue) {
            if ($attributeValue instanceof self) {
                $normalized[] = $attributeValue;
                continue;
            }

            Assert::that($attributeName)->string()->notEmpty();

            $normalized[] = new self(
                attributeName: (string) $attributeName,
                attributeValue: $attributeValue,
            );
        }

        return $normalized;
    }

    /**
     * @param list<self>|array<string, int|string|UuidInterface|list<string>> $attributes
     * @return array<string, list<string>>
     */
    public static function normalizeCollectionToMap(array $attributes): array
    {
        $normalized = [];

        foreach (self::normalizeCollection(attributes: $attributes) as $attribute) {
            $attributeName = $attribute->getAttributeName();
            $normalized[$attributeName] = array_values(array_merge(
                $normalized[$attributeName] ?? [],
                $attribute->getNormalizedValues(),
            ));
        }

        return $normalized;
    }
}
