<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateUserProfileAttributeDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $attribute = new AttributeDto(name: 'external-user-id');
        $dto = new CreateUserProfileAttributeDto(
            realm: 'master',
            attribute: $attribute,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($attribute, $dto->getAttribute());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreateUserProfileAttributeDto(
            realm: '',
            attribute: new AttributeDto(name: 'external-user-id'),
        );
    }
}

