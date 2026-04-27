<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeRequiredDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileGroupDto;
use PHPUnit\Framework\TestCase;

final class UserProfileDtoTest extends TestCase
{
    public function testFromArrayToArrayAndAttributeOperations(): void
    {
        $dto = UserProfileDto::fromArray(
            [
                'unmanagedAttributePolicy' => 'ENABLED',
                'attributes' => [
                    [
                        'group' => 'user-metadata',
                        'name' => 'username',
                        'displayName' => '${username}',
                        'validations' => [],
                        'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                        'multivalued' => false,
                        'annotations' => [],
                    ],
                ],
                'groups' => [
                    [
                        'name' => 'user-metadata',
                        'displayHeader' => 'User metadata',
                        'displayDescription' => 'Attributes, which refer to user metadata',
                        'annotations' => [
                            'collapsed' => false,
                        ],
                    ],
                ],
            ],
        );

        self::assertCount(1, $dto->getAttributes());
        self::assertCount(1, $dto->getGroups());
        self::assertTrue($dto->hasAttribute('username'));
        self::assertSame(['unmanagedAttributePolicy' => 'ENABLED'], $dto->getExtra());

        $created = $dto->withAppendedAttribute(
            new AttributeDto(
                name: 'external-user-id',
                permissions: ['view' => ['admin'], 'edit' => ['admin']],
                required: new AttributeRequiredDto(
                    roles: ['admin'],
                ),
            ),
        );
        self::assertTrue($created->hasAttribute('external-user-id'));

        $createdFromServer = UserProfileDto::fromArray(
            [
                'unmanagedAttributePolicy' => 'ENABLED',
                'attributes' => [
                    [
                        'group' => 'user-metadata',
                        'name' => 'username',
                        'displayName' => '${username}',
                        'validations' => [],
                        'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                        'multivalued' => false,
                        'annotations' => [],
                    ],
                    [
                        'required' => [
                            'roles' => ['admin'],
                        ],
                        'selector' => [
                            'scopes' => ['openid'],
                        ],
                        'name' => 'external-user-id',
                        'validations' => [
                            'custom-validator' => [
                                'enabled' => true,
                            ],
                        ],
                        'permissions' => ['view' => ['admin'], 'edit' => ['admin']],
                        'multivalued' => false,
                        'annotations' => [],
                    ],
                ],
                'groups' => [
                    [
                        'name' => 'user-metadata',
                        'displayHeader' => 'User metadata',
                        'displayDescription' => 'Attributes, which refer to user metadata',
                        'annotations' => [
                            'collapsed' => false,
                        ],
                    ],
                ],
            ],
        );

        $updated = $createdFromServer->withUpdatedAttribute(
            new AttributeDto(
                name: 'external-user-id',
                displayName: 'External user id',
                permissions: ['view' => ['admin'], 'edit' => ['admin']],
                required: new AttributeRequiredDto(
                    roles: ['manager'],
                ),
            ),
        );
        self::assertTrue($updated->hasAttribute('external-user-id'));
        self::assertSame(
            [
                'selector' => [
                    'scopes' => ['openid'],
                ],
                'name' => 'external-user-id',
                'validations' => [
                    'custom-validator' => [
                        'enabled' => true,
                    ],
                ],
                'permissions' => ['view' => ['admin'], 'edit' => ['admin']],
                'multivalued' => false,
                'annotations' => [],
                'required' => [
                    'roles' => ['manager'],
                ],
                'displayName' => 'External user id',
            ],
            $updated->getAttributes()[1]->toArray(),
        );

        $deleted = $updated->withoutAttribute('external-user-id');
        self::assertFalse($deleted->hasAttribute('external-user-id'));

        self::assertSame(
            [
                'unmanagedAttributePolicy' => 'ENABLED',
                'attributes' => [
                    [
                        'group' => 'user-metadata',
                        'name' => 'username',
                        'validations' => [],
                        'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                        'multivalued' => false,
                        'annotations' => [],
                        'displayName' => '${username}',
                    ],
                ],
                'groups' => [
                    [
                        'name' => 'user-metadata',
                        'displayHeader' => 'User metadata',
                        'displayDescription' => 'Attributes, which refer to user metadata',
                        'annotations' => [
                            'collapsed' => false,
                        ],
                    ],
                ],
            ],
            $dto->toArray(),
        );
    }

    public function testConstructorAcceptsTypedCollections(): void
    {
        $dto = new UserProfileDto(
            attributes: [new AttributeDto(name: 'username')],
            groups: [new UserProfileGroupDto(name: 'user-metadata')],
        );

        self::assertCount(1, $dto->getAttributes());
        self::assertCount(1, $dto->getGroups());
    }
}
