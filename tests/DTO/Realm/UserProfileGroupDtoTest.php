<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm;

use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfileGroupDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserProfileGroupDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = UserProfileGroupDto::fromArray(
            [
                'name' => 'user-metadata',
                'displayHeader' => 'User metadata',
                'displayDescription' => 'Attributes, which refer to user metadata',
            ],
        );

        self::assertSame('user-metadata', $dto->getName());
        self::assertSame('User metadata', $dto->getDisplayHeader());
        self::assertSame('Attributes, which refer to user metadata', $dto->getDisplayDescription());
        self::assertSame(
            [
                'name' => 'user-metadata',
                'displayHeader' => 'User metadata',
                'displayDescription' => 'Attributes, which refer to user metadata',
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UserProfileGroupDto(name: '');
    }
}

