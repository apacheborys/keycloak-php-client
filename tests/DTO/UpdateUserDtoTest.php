<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateUserDtoTest extends TestCase
{
    public function testGettersAndPayload(): void
    {
        $dto = new UpdateUserDto(
            realm: 'master',
            userId: '92a372d5-c338-4e77-a1b3-08771241036e',
            profile: new UpdateUserProfileDto(
                username: 'oleg@example.com',
                email: 'oleg.new@example.com',
                firstName: 'Oleg',
                emailVerified: true,
            ),
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $dto->getUserId());
        self::assertSame('oleg@example.com', $dto->getProfile()->getUsername());
        self::assertSame('oleg.new@example.com', $dto->getProfile()->getEmail());
        self::assertSame(
            [
                'username' => 'oleg@example.com',
                'email' => 'oleg.new@example.com',
                'emailVerified' => true,
                'firstName' => 'Oleg',
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidUserIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateUserDto(
            realm: 'master',
            userId: 'not-a-uuid',
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'user@example.com',
            ),
        );
    }
}
