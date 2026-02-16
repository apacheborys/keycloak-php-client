<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Oidc;

use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;

final class OidcComplianceTest extends TestCase
{
    public function testPasswordGrantRequestShape(): void
    {
        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
            scope: 'openid profile',
            grantType: OidcGrantType::PASSWORD,
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

    public function testRefreshTokenGrantRequestShape(): void
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

    public function testTokenResponseParsesIdTokenWhenPresent(): void
    {
        $jwt = JwtTestFactory::buildJwtToken();
        $data = [
            'access_token' => $jwt,
            'expires_in' => 3600,
            'refresh_expires_in' => 1800,
            'refresh_token' => 'refresh-token',
            'token_type' => 'Bearer',
            'not-before-policy' => 0,
            'scope' => 'openid profile',
            'id_token' => $jwt,
        ];

        $dto = OidcTokenResponseDto::fromArray(data: $data);

        self::assertInstanceOf(JsonWebToken::class, $dto->getAccessToken());
        self::assertSame('refresh-token', $dto->getRefreshToken());
        self::assertInstanceOf(JsonWebToken::class, $dto->getIdToken());
    }

}
