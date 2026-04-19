<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateUserProfileAttributeDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $attribute = new AttributeDto(name: 'external-user-id');
        $dto = new UpdateUserProfileAttributeDto(
            realm: 'master',
            attribute: $attribute,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($attribute, $dto->getAttribute());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateUserProfileAttributeDto(
            realm: '',
            attribute: new AttributeDto(name: 'external-user-id'),
        );
    }
}

