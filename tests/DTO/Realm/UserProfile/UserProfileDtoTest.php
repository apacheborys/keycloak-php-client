<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileGroupDto;
use PHPUnit\Framework\TestCase;

final class UserProfileDtoTest extends TestCase
{
    public function testFromArrayToArrayAndAttributeOperations(): void
    {
        $dto = UserProfileDto::fromArray(
            [
                'attributes' => [
                    [
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
                    ],
                ],
            ],
        );

        self::assertCount(1, $dto->getAttributes());
        self::assertCount(1, $dto->getGroups());
        self::assertTrue($dto->hasAttribute('username'));

        $created = $dto->withAppendedAttribute(
            new AttributeDto(
                name: 'external-user-id',
                permissions: ['view' => ['admin'], 'edit' => ['admin']],
            ),
        );
        self::assertTrue($created->hasAttribute('external-user-id'));

        $updated = $created->withUpdatedAttribute(
            new AttributeDto(
                name: 'external-user-id',
                displayName: 'External user id',
                permissions: ['view' => ['admin'], 'edit' => ['admin']],
            ),
        );
        self::assertTrue($updated->hasAttribute('external-user-id'));

        $deleted = $updated->withoutAttribute('external-user-id');
        self::assertFalse($deleted->hasAttribute('external-user-id'));

        self::assertSame(
            [
                'attributes' => [
                    [
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

