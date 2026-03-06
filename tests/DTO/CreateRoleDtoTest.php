<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateRoleDtoTest extends TestCase
{
    public function testGettersAndPayload(): void
    {
        $role = new RoleDto(
            name: 'my-role',
            description: 'Role for test',
            composite: false,
            clientRole: false,
        );

        $dto = new CreateRoleDto(
            realm: 'master',
            role: $role,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($role, $dto->getRole());
        self::assertSame(
            [
                'name' => 'my-role',
                'composite' => false,
                'clientRole' => false,
                'description' => 'Role for test',
            ],
            $dto->toArray(),
        );
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreateRoleDto(
            realm: '',
            role: new RoleDto(name: 'my-role'),
        );
    }
}
