<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\PreparedUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PreparedUserRolesDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $roles = [
            new RoleDto(name: 'app-user'),
            new RoleDto(name: 'app-admin'),
        ];

        $dto = new PreparedUserRolesDto(
            roles: $roles,
            roleCreationAllowed: true,
        );

        self::assertSame($roles, $dto->getRoles());
        self::assertTrue($dto->isRoleCreationAllowed());
    }

    public function testInvalidRoleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line */
        new PreparedUserRolesDto(roles: ['app-user']);
    }
}
