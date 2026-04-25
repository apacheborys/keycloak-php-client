<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeRequiredDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttributeRequiredDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = AttributeRequiredDto::fromArray(
            [
                'roles' => ['admin', 'user'],
                'scopes' => ['openid'],
                'customRule' => [
                    'enabled' => true,
                ],
            ],
        );

        self::assertSame(['admin', 'user'], $dto->getRoles());
        self::assertSame(['openid'], $dto->getScopes());
        self::assertSame(['customRule' => ['enabled' => true]], $dto->getExtra());
        self::assertFalse($dto->isAlways());
        self::assertSame(
            [
                'customRule' => [
                    'enabled' => true,
                ],
                'roles' => ['admin', 'user'],
                'scopes' => ['openid'],
            ],
            $dto->toArray(),
        );
    }

    public function testEmptyRequiredConfigMeansAlwaysRequired(): void
    {
        $dto = AttributeRequiredDto::fromArray([]);

        self::assertTrue($dto->isAlways());
        self::assertSame([], $dto->toArray());
    }

    public function testInvalidRoleTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AttributeRequiredDto::fromArray(
            [
                'roles' => ['admin', 123],
            ],
        );
    }
}
