<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GetUserProfileDtoTest extends TestCase
{
    public function testGetRealm(): void
    {
        $dto = new GetUserProfileDto(realm: 'master');

        self::assertSame('master', $dto->getRealm());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GetUserProfileDto(realm: '');
    }
}

