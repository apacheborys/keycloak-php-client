<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateUserProfileDtoTest extends TestCase
{
    public function testToArrayContainsRequiredAndOnlyProvidedOptionalFields(): void
    {
        $dto = new UpdateUserProfileDto(
            username: 'user@example.com',
            email: 'new@example.com',
            firstName: 'Oleg',
            enabled: true,
            attributes: [
                new AttributeValueDto(
                    attributeName: 'external-user-id',
                    attributeValue: 'external-id-2',
                ),
                new AttributeValueDto(
                    attributeName: 'locale',
                    attributeValue: ['uk'],
                ),
            ],
        );

        self::assertSame('user@example.com', $dto->getUsername());
        self::assertSame('new@example.com', $dto->getEmail());
        self::assertCount(2, $dto->getAttributeDtos() ?? []);
        self::assertSame(
            [
                'external-user-id' => ['external-id-2'],
                'locale' => ['uk'],
            ],
            $dto->getAttributes(),
        );
        self::assertSame(
            [
                'username' => 'user@example.com',
                'email' => 'new@example.com',
                'enabled' => true,
                'firstName' => 'Oleg',
                'attributes' => [
                    'external-user-id' => ['external-id-2'],
                    'locale' => ['uk'],
                ],
            ],
            $dto->toArray(),
        );
    }

    public function testAttributesMapInputRemainsSupported(): void
    {
        $dto = new UpdateUserProfileDto(
            username: 'user@example.com',
            attributes: [
                'external-user-id' => 42,
                'locale' => ['uk'],
            ],
        );

        self::assertSame(
            [
                'external-user-id' => ['42'],
                'locale' => ['uk'],
            ],
            $dto->getAttributes(),
        );
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateUserProfileDto(
            username: 'user@example.com',
            email: 'invalid-email',
        );
    }

    public function testInvalidAttributeValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateUserProfileDto(
            username: 'user@example.com',
            attributes: [
                'external-user-id' => [true],
            ],
        );
    }
}
