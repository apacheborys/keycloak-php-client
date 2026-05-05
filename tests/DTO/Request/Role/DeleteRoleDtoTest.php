<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\Role;

use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeleteRoleDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $dto = new DeleteRoleDto(
            realm: 'master',
            roleName: 'my-role',
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('my-role', $dto->getRoleName());
    }

    public function testEmptyRoleNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeleteRoleDto(
            realm: 'master',
            roleName: '',
        );
    }
}
