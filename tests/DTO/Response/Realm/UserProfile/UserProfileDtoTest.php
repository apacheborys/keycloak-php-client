<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Response\Realm\UserProfile;

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

    public function testJsonSerializeEncodesEmptyMapsAsJsonObjects(): void
    {
        // Shape as returned by Keycloak 26: validator configs and annotations are JSON objects, often empty.
        $keycloakJson = '{"unmanagedAttributePolicy":"ENABLED","attributes":[{"name":"email",'
            . '"validations":{"email":{},"length":{"max":255}},"required":{},'
            . '"permissions":{"view":["admin","user"],"edit":["admin","user"]},"multivalued":false,"annotations":{}}],'
            . '"groups":[{"name":"user-metadata","annotations":{}}]}';

        $dto = UserProfileDto::fromArray(json_decode($keycloakJson, true, flags: JSON_THROW_ON_ERROR));
        $encoded = json_encode($dto, JSON_THROW_ON_ERROR);

        // toArray() keeps plain PHP arrays, which json_encode() would turn into `[]` and Keycloak would reject.
        self::assertSame([], $dto->getAttributes()[0]->toArray()['annotations']);
        self::assertSame([], $dto->getAttributes()[0]->toArray()['validations']['email']);

        self::assertStringContainsString('"email":{}', $encoded);
        self::assertStringContainsString('"length":{"max":255}', $encoded);
        self::assertStringContainsString('"required":{}', $encoded);
        self::assertStringContainsString('"annotations":{}', $encoded);
        self::assertStringNotContainsString('[]', $encoded);

        // An attribute without validators or required rule still encodes every map member as an object.
        $minimal = json_encode(new UserProfileDto(attributes: [new AttributeDto(name: 'plain')]), JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"validations":{}', $minimal);
        self::assertStringContainsString('"annotations":{}', $minimal);
        self::assertStringContainsString('"permissions":{"view":[],"edit":[]}', $minimal);
        self::assertStringContainsString('"groups":[]', $minimal);
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
