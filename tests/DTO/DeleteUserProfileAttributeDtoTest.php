<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserProfileAttributeDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeleteUserProfileAttributeDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $dto = new DeleteUserProfileAttributeDto(
            realm: 'master',
            attributeName: 'external-user-id',
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('external-user-id', $dto->getAttributeName());
    }

    public function testEmptyAttributeNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeleteUserProfileAttributeDto(
            realm: 'master',
            attributeName: '',
        );
    }
}

