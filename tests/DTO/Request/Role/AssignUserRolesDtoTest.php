<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\Role;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use TypeError;

final class AssignUserRolesDtoTest extends TestCase
{
    public function testGettersAndPayload(): void
    {
        $roles = [
            new RoleDto(
                id: Uuid::fromString('7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8'),
                name: 'admin',
                composite: false,
                clientRole: false,
                containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
            ),
        ];

        $dto = new AssignUserRolesDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            roles: $roles,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $dto->getUserId()->toString());
        self::assertSame($roles, $dto->getRoles());
        self::assertSame(
            [
                [
                    'name' => 'admin',
                    'composite' => false,
                    'clientRole' => false,
                    'id' => '7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8',
                    'containerId' => '992b5dcf-1cdc-4b69-8fe2-0beaec437b17',
                ],
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidUserIdTypeThrows(): void
    {
        $this->expectException(TypeError::class);

        new AssignUserRolesDto(
            realm: 'master',
            userId: 'not-a-uuid',
            roles: [],
        );
    }
}
