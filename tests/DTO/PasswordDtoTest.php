<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordDtoTest extends TestCase
{
    public function testPlainPasswordOnlyIsAccepted(): void
    {
        $dto = new PasswordDto(plainPassword: 'secret');

        self::assertSame('secret', $dto->getPlainPassword());
        self::assertNull($dto->getHashedPassword());
        self::assertNull($dto->getHashAlgorithm());
    }

    public function testHashedPasswordWithAlgorithmIsAccepted(): void
    {
        $dto = new PasswordDto(
            hashedPassword: 'hash',
            hashAlgorithm: HashAlgorithm::BCRYPT,
            hashIterations: 10,
            hashSalt: 'salt',
        );

        self::assertNull($dto->getPlainPassword());
        self::assertSame('hash', $dto->getHashedPassword());
        self::assertSame(HashAlgorithm::BCRYPT, $dto->getHashAlgorithm());
        self::assertSame(10, $dto->getHashIterations());
        self::assertSame('salt', $dto->getHashSalt());
    }

    public function testInvalidCombinationThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PasswordDto(
            hashedPassword: 'hash',
            hashAlgorithm: HashAlgorithm::BCRYPT,
            hashIterations: null,
            hashSalt: null,
        );
    }
}
