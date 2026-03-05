<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GetRolesDtoTest extends TestCase
{
    public function testGetter(): void
    {
        $dto = new GetRolesDto(realm: 'master');

        self::assertSame('master', $dto->getRealm());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GetRolesDto(realm: '');
    }
}
