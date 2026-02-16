<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Oidc;

use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;

final class OidcComplianceTest extends TestCase
{
    public function testPasswordGrantRequestShape(): void
    {
        $dto = new LoginUserDto(
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
        $dto = new LoginUserDto(
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
        $jwt = $this->buildJwtToken();
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

        $dto = RequestAccessDto::fromArray(data: $data);

        self::assertInstanceOf(JsonWebToken::class, $dto->getAccessToken());
        self::assertSame('refresh-token', $dto->getRefreshToken());
        self::assertInstanceOf(JsonWebToken::class, $dto->getIdToken());
    }

    private function buildJwtToken(): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'kid',
        ];
        $payload = [
            'exp' => time() + 3600,
            'iat' => time(),
            'jti' => 'f9b4b801-bb78-4167-be60-b42d453332e7',
            'iss' => 'http://localhost:8080/realms/master',
            'aud' => ['account'],
            'sub' => '92a372d5-c338-4e77-a1b3-08771241036e',
            'typ' => 'Bearer',
            'azp' => 'backend',
            'acr' => 1,
            'realm_access' => ['roles' => ['role']],
            'resource_access' => [
                'backend' => ['roles' => ['role']],
                'account' => ['roles' => ['role']],
            ],
            'scope' => 'email profile',
            'email_verified' => true,
            'clientHost' => '127.0.0.1',
            'preferred_username' => 'oleg@example.com',
            'clientAddress' => '127.0.0.1',
            'client_id' => 'backend',
        ];

        return $this->base64UrlEncode($header) . '.' .
            $this->base64UrlEncode($payload) . '.' .
            $this->base64UrlEncode(['sig' => 'signature']);
    }

    private function base64UrlEncode(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
