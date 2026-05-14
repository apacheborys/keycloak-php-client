<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Oidc;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use PHPUnit\Framework\TestCase;

final class OidcComplianceTest extends TestCase
{
    public function testClientCredentialsGrantRequestShape(): void
    {
        $dto = OidcTokenRequestDto::forClientCredentials(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            scope: 'openid profile',
        );

        self::assertSame(
            [
                'grant_type' => 'client_credentials',
                'client_id' => 'backend',
                'client_secret' => 'secret',
                'scope' => 'openid profile',
            ],
            $dto->toFormParams(),
        );
    }

    public function testPasswordGrantRequestShape(): void
    {
        $dto = OidcTokenRequestDto::forPasswordGrant(
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

    public function testRefreshTokenGrantRequestShape(): void
    {
        $dto = OidcTokenRequestDto::forRefreshToken(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            refreshToken: 'refresh-token',
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
