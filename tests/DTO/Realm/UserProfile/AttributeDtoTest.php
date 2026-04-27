<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeRequiredDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttributeDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = AttributeDto::fromArray(
            [
                'group' => 'user-metadata',
                'required' => [
                    'roles' => ['admin'],
                ],
                'selector' => [
                    'scopes' => ['openid'],
                ],
                'defaultValue' => 'generated',
                'name' => 'external-user-id',
                'displayName' => 'External user id',
                'validations' => [
                    'length' => [
                        'min' => '3',
                        'max' => '255',
                    ],
                    'custom-validator' => [
                        'enabled' => true,
                    ],
                ],
                'permissions' => [
                    'view' => ['admin', 'manager'],
                    'edit' => ['admin', 'manager'],
                ],
                'multivalued' => false,
                'annotations' => [
                    'inputType' => 'text',
                    'ui' => [
                        'section' => 'metadata',
                    ],
                    'required' => true,
                ],
            ],
        );

        self::assertSame('external-user-id', $dto->getName());
        self::assertSame('External user id', $dto->getDisplayName());
        self::assertSame(['admin', 'manager'], $dto->getPermissions()['view']);
        self::assertSame(['admin', 'manager'], $dto->getPermissions()['edit']);
        self::assertFalse($dto->isMultivalued());
        self::assertTrue($dto->isRequired());
        self::assertSame(['admin'], $dto->getRequired()?->getRoles());
        self::assertSame('text', $dto->getAnnotations()['inputType'] ?? null);
        self::assertSame(['section' => 'metadata'], $dto->getAnnotations()['ui'] ?? null);
        self::assertTrue((bool) ($dto->getAnnotations()['required'] ?? false));
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
                'enabled' => true,
            ],
            $dto->getValidations()['custom-validator'] ?? null,
        );
        self::assertSame(
            [
                'group' => 'user-metadata',
                'selector' => [
                    'scopes' => ['openid'],
                ],
                'defaultValue' => 'generated',
            ],
            $dto->getExtra(),
        );

        self::assertSame(
            [
                'group' => 'user-metadata',
                'selector' => [
                    'scopes' => ['openid'],
                ],
                'defaultValue' => 'generated',
                'name' => 'external-user-id',
                'validations' => [
                    'length' => [
                        'min' => '3',
                        'max' => '255',
                    ],
                    'custom-validator' => [
                        'enabled' => true,
                    ],
                ],
                'permissions' => [
                    'view' => ['admin', 'manager'],
                    'edit' => ['admin', 'manager'],
                ],
                'multivalued' => false,
                'annotations' => [
                    'inputType' => 'text',
                    'ui' => [
                        'section' => 'metadata',
                    ],
                    'required' => true,
                ],
                'required' => [
                    'roles' => ['admin'],
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

    public function testInvalidPermissionValueTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttributeDto::fromArray(
            [
                'name' => 'external-user-id',
                'permissions' => [
                    'view' => ['admin'],
                    'edit' => [null],
                ],
            ],
        );
    }

    public function testWithPreservedUnknownFieldsFromKeepsExistingUnknownData(): void
    {
        $current = AttributeDto::fromArray(
            [
                'name' => 'external-user-id',
                'permissions' => [
                    'view' => ['admin'],
                    'edit' => ['admin'],
                ],
                'required' => [
                    'roles' => ['admin'],
                ],
                'validations' => [
                    'custom-validator' => [
                        'flag' => true,
                    ],
                ],
            ],
        );

        $updated = (new AttributeDto(
            name: 'external-user-id',
            displayName: 'External user id',
            permissions: [
                'view' => ['admin'],
                'edit' => ['admin'],
            ],
            required: new AttributeRequiredDto(
                roles: ['manager'],
            ),
        ))->withPreservedUnknownFieldsFrom($current);

        self::assertSame(
            [],
            $updated->getExtra(),
        );
        self::assertSame(['manager'], $updated->getRequired()?->getRoles());
        self::assertSame(
            [
                'custom-validator' => [
                    'flag' => true,
                ],
            ],
            $updated->getValidations(),
        );
    }
}
