<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Response\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileGroupDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserProfileGroupDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = UserProfileGroupDto::fromArray(
            [
                'customGroupProperty' => [
                    'enabled' => true,
                ],
                'name' => 'user-metadata',
                'displayHeader' => 'User metadata',
                'displayDescription' => 'Attributes, which refer to user metadata',
                'annotations' => [
                    'collapsed' => false,
                    'ui' => [
                        'section' => 'metadata',
                    ],
                ],
            ],
        );

        self::assertSame('user-metadata', $dto->getName());
        self::assertSame('User metadata', $dto->getDisplayHeader());
        self::assertSame('Attributes, which refer to user metadata', $dto->getDisplayDescription());
        self::assertFalse((bool) ($dto->getAnnotations()['collapsed'] ?? true));
        self::assertSame(['section' => 'metadata'], $dto->getAnnotations()['ui'] ?? null);
        self::assertSame(['customGroupProperty' => ['enabled' => true]], $dto->getExtra());
        self::assertSame(
            [
                'customGroupProperty' => [
                    'enabled' => true,
                ],
                'name' => 'user-metadata',
                'displayHeader' => 'User metadata',
                'displayDescription' => 'Attributes, which refer to user metadata',
                'annotations' => [
                    'collapsed' => false,
                    'ui' => [
                        'section' => 'metadata',
                    ],
                ],
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
