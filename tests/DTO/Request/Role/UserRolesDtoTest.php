<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\Role;

use Apacheborys\KeycloakPhpClient\DTO\Request\Role\UserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use PHPUnit\Framework\TestCase;

final class UserRolesDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $role = new RoleDto(name: 'role-user');
        $dto = new UserRolesDto(
            realm: 'master',
            roles: [$role],
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame([$role], $dto->getRoles());
    }

    public function testAllowsNullRoles(): void
    {
        $dto = new UserRolesDto(realm: 'master');

        self::assertNull($dto->getRoles());
    }
}
