<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakGrantType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LoginUserDtoTest extends TestCase
{
    public function testToFormParams(): void
    {
        $dto = new LoginUserDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
        );

        self::assertSame(
            [
                'grant_type' => 'password',
                'client_id' => 'backend',
                'client_secret' => 'secret',
                'username' => 'oleg@example.com',
                'password' => 'Roadsurfer!2026',
            ],
            $dto->toFormParams(),
        );
        self::assertSame('master', $dto->getRealm());
    }

    public function testPasswordGrantRequiresCredentials(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LoginUserDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            grantType: KeycloakGrantType::PASSWORD,
        );
    }

    public function testRefreshTokenFormParams(): void
    {
        $dto = new LoginUserDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            refreshToken: 'refresh-token',
            grantType: KeycloakGrantType::REFRESH_TOKEN,
        );

        self::assertSame(
            [
                'grant_type' => 'refresh_token',
                'client_id' => 'backend',
                'client_secret' => 'secret',
                'refresh_token' => 'refresh-token',
            ],
            $dto->toFormParams(),
        );
    }

    public function testRefreshTokenRequiresToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LoginUserDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            grantType: KeycloakGrantType::REFRESH_TOKEN,
        );
    }
}
