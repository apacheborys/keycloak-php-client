<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\Role;

use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use TypeError;

final class GetUserAvailableRolesDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $dto = new GetUserAvailableRolesDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $dto->getUserId()->toString());
    }

    public function testInvalidUserIdTypeThrows(): void
    {
        $this->expectException(TypeError::class);

        new GetUserAvailableRolesDto(
            realm: 'master',
            userId: 'not-a-uuid',
        );
    }
}
