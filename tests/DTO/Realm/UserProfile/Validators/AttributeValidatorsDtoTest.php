<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm\UserProfile\Validators;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorType;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorsDto;
use PHPUnit\Framework\TestCase;

final class AttributeValidatorsDtoTest extends TestCase
{
    public function testFromKeycloakArrayAndToKeycloakArray(): void
    {
        $dto = AttributeValidatorsDto::fromKeycloakArray(
            [
                'length' => [
                    'min' => '3',
                    'max' => '255',
                ],
                'pattern' => [
                    'pattern' => '^[0-9a-f]{8}(?:\\-[0-9a-f]{4}){3}-[0-9a-f]{12}$',
                ],
            ],
        );

        self::assertTrue($dto->has(AttributeValidatorType::LENGTH));
        self::assertTrue($dto->has(AttributeValidatorType::PATTERN));
        self::assertFalse($dto->has(AttributeValidatorType::EMAIL));

        self::assertSame(
            [
                'length' => [
                    'min' => '3',
                    'max' => '255',
                ],
                'pattern' => [
                    'pattern' => '^[0-9a-f]{8}(?:\\-[0-9a-f]{4}){3}-[0-9a-f]{12}$',
                ],
            ],
            $dto->toKeycloakArray(),
        );
    }

    public function testUnknownValidatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AttributeValidatorsDto::fromKeycloakArray(
            [
                'unknown-validator' => [],
            ],
        );
    }
}
