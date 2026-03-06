<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateUserProfileDtoTest extends TestCase
{
    public function testToArray(): void
    {
        $dto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
            roles: [new RoleDto(name: 'admin')],
        );

        self::assertSame(
            [
                'username' => 'user@example.com',
                'email' => 'user@example.com',
                'emailVerified' => true,
                'enabled' => true,
                'firstName' => 'User',
                'lastName' => 'Example',
            ],
            $dto->toArray(),
        );
        self::assertSame('master', $dto->getRealm());
        self::assertSame('user@example.com', $dto->getEmail());
        self::assertCount(1, $dto->getRoles());
        self::assertSame('admin', $dto->getRoles()[0]->getName());
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'invalid-email',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
    }
}
