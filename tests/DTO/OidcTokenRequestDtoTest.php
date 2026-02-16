<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OidcTokenRequestDtoTest extends TestCase
{
    public function testToFormParams(): void
    {
        $dto = new OidcTokenRequestDto(
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

        new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            grantType: OidcGrantType::PASSWORD,
        );
    }

    public function testRefreshTokenFormParams(): void
    {
        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            refreshToken: 'refresh-token',
            grantType: OidcGrantType::REFRESH_TOKEN,
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

        new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            grantType: OidcGrantType::REFRESH_TOKEN,
        );
    }

    public function testScopeIsIncludedWhenProvided(): void
    {
        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
            scope: 'openid profile',
        );

        self::assertSame(
            [
                'grant_type' => 'password',
                'client_id' => 'backend',
                'client_secret' => 'secret',
                'scope' => 'openid profile',
                'username' => 'oleg@example.com',
                'password' => 'Roadsurfer!2026',
            ],
            $dto->toFormParams(),
        );
    }
}
