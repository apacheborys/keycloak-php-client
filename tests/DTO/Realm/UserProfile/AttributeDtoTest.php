<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\Validators\AttributeValidatorType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttributeDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = AttributeDto::fromArray(
            [
                'name' => 'external-user-id',
                'displayName' => 'External user id',
                'validations' => [
                    'length' => [
                        'min' => '3',
                        'max' => '255',
                    ],
                ],
                'permissions' => [
                    'view' => ['admin', 'user'],
                    'edit' => ['admin', 'user'],
                ],
                'multivalued' => false,
                'annotations' => [
                    'inputType' => 'text',
                ],
            ],
        );

        self::assertSame('external-user-id', $dto->getName());
        self::assertSame('External user id', $dto->getDisplayName());
        self::assertSame(['admin', 'user'], $dto->getPermissions()['view']);
        self::assertSame(['admin', 'user'], $dto->getPermissions()['edit']);
        self::assertFalse($dto->isMultivalued());
        self::assertSame('text', $dto->getAnnotations()['inputType'] ?? null);
        self::assertTrue($dto->hasValidator(AttributeValidatorType::LENGTH));
        self::assertSame(
            [
                'min' => '3',
                'max' => '255',
            ],
            $dto->getValidations()['length'] ?? null,
        );

        self::assertSame(
            [
                'name' => 'external-user-id',
                'validations' => [
                    'length' => [
                        'min' => '3',
                        'max' => '255',
                    ],
                ],
                'permissions' => [
                    'view' => ['admin', 'user'],
                    'edit' => ['admin', 'user'],
                ],
                'multivalued' => false,
                'annotations' => [
                    'inputType' => 'text',
                ],
                'displayName' => 'External user id',
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AttributeDto(name: '');
    }

    public function testInvalidPermissionValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttributeDto::fromArray(
            [
                'name' => 'external-user-id',
                'permissions' => [
                    'view' => ['admin'],
                    'edit' => ['manager'],
                ],
            ],
        );
    }

    public function testUnknownValidatorTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AttributeDto::fromArray(
            [
                'name' => 'external-user-id',
                'validations' => [
                    'unknown-validator' => [],
                ],
            ],
        );
    }
}
