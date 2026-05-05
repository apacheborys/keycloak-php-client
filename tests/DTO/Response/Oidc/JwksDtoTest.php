<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Response\Oidc;

use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwksDto;
use PHPUnit\Framework\TestCase;

final class JwksDtoTest extends TestCase
{
    public function testFromArrayAndFindByKid(): void
    {
        $dto = JwksDto::fromArray(
            [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'kid' => 'kid-1',
                        'use' => 'sig',
                        'alg' => 'RS256',
                        'n' => 'modulus',
                        'e' => 'AQAB',
                        'x5c' => ['certificate'],
                    ],
                ],
            ]
        );

        self::assertCount(1, $dto->getKeys());
        self::assertInstanceOf(JwkDto::class, $dto->findByKid('kid-1'));
        self::assertNull($dto->findByKid('missing'));
    }

    public function testToArray(): void
    {
        $dto = new JwksDto(
            keys: [
                new JwkDto(
                    kty: 'RSA',
                    kid: 'kid-1',
                    use: 'sig',
                    alg: 'RS256',
                    n: 'modulus',
                    e: 'AQAB',
                    x5c: ['certificate'],
                ),
            ],
        );

        self::assertSame(
            [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'kid' => 'kid-1',
                        'use' => 'sig',
                        'alg' => 'RS256',
                        'n' => 'modulus',
                        'e' => 'AQAB',
                        'x5c' => ['certificate'],
                    ],
                ],
            ],
            $dto->toArray(),
        );
    }
}
