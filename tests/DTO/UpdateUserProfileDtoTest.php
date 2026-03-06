<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateUserProfileDtoTest extends TestCase
{
    public function testToArrayContainsRequiredAndOnlyProvidedOptionalFields(): void
    {
        $dto = new UpdateUserProfileDto(
            username: 'user@example.com',
            email: 'new@example.com',
            firstName: 'Oleg',
            enabled: true,
            roles: [new RoleDto(name: 'admin')],
        );

        self::assertSame('user@example.com', $dto->getUsername());
        self::assertSame('new@example.com', $dto->getEmail());
        self::assertNotNull($dto->getRoles());
        self::assertCount(1, $dto->getRoles());
        self::assertSame('admin', $dto->getRoles()[0]->getName());
        self::assertSame(
            [
                'username' => 'user@example.com',
                'email' => 'new@example.com',
                'enabled' => true,
                'firstName' => 'Oleg',
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateUserProfileDto(
            username: 'user@example.com',
            email: 'invalid-email',
        );
    }
}
