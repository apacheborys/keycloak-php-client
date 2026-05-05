<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\AttributeValueDto;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class AttributeValueDtoTest extends TestCase
{
    public function testGettersAndNormalizedValuesForScalar(): void
    {
        $attributeValue = Uuid::fromString('a912d2b1-0b23-4c50-89f4-41c52f286cb8');
        $dto = new AttributeValueDto(
            attributeName: 'external-user-id',
            attributeValue: $attributeValue,
        );

        self::assertSame('external-user-id', $dto->getAttributeName());
        self::assertSame($attributeValue, $dto->getAttributeValue());
        self::assertSame(
            ['a912d2b1-0b23-4c50-89f4-41c52f286cb8'],
            $dto->getNormalizedValues(),
        );
    }

    public function testNormalizedValuesForMultiValueAttribute(): void
    {
        $dto = new AttributeValueDto(
            attributeName: 'locale',
            attributeValue: ['en', 'de'],
        );

        self::assertSame(['en', 'de'], $dto->getNormalizedValues());
    }

    public function testNormalizedValuesForStringScalar(): void
    {
        $dto = new AttributeValueDto(
            attributeName: 'external-user-id',
            attributeValue: 'external-id-1',
        );

        self::assertSame(['external-id-1'], $dto->getNormalizedValues());
    }

    public function testNormalizeCollectionToMapAggregatesAttributeDtos(): void
    {
        self::assertSame(
            [
                'locale' => ['en', 'de'],
                'external-user-id' => ['42'],
            ],
            AttributeValueDto::normalizeCollectionToMap(
                attributes: [
                    new AttributeValueDto(
                        attributeName: 'locale',
                        attributeValue: 'en',
                    ),
                    new AttributeValueDto(
                        attributeName: 'locale',
                        attributeValue: ['de'],
                    ),
                    'external-user-id' => 42,
                ],
            ),
        );
    }
}
