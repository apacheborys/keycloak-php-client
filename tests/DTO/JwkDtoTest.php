<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JwkDtoTest extends TestCase
{
    public function testFromArrayAndGetters(): void
    {
        $dto = JwkDto::fromArray(
            [
                'kty' => 'RSA',
                'kid' => 'kid-1',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => 'modulus',
                'e' => 'AQAB',
                'x5c' => ['certificate'],
            ]
        );

        self::assertSame('RSA', $dto->getKty());
        self::assertSame('kid-1', $dto->getKid());
        self::assertSame('sig', $dto->getUse());
        self::assertSame('RS256', $dto->getAlg());
        self::assertSame('modulus', $dto->getN());
        self::assertSame('AQAB', $dto->getE());
        self::assertSame('certificate', $dto->getFirstCertificate());
    }

    public function testMissingRequiredFieldThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JwkDto::fromArray(
            [
                'kty' => 'RSA',
                'kid' => 'kid-1',
                'use' => 'sig',
                'alg' => 'RS256',
                'e' => 'AQAB',
            ]
        );
    }
}
