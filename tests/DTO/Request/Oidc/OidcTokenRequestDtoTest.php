<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\Oidc;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OidcTokenRequestDtoTest extends TestCase
{
    public function testForPasswordGrantBuildsExpectedFormParams(): void
    {
        $dto = OidcTokenRequestDto::forPasswordGrant(
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

    public function testForClientCredentialsBuildsExpectedFormParams(): void
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
        self::assertSame(OidcGrantType::CLIENT_CREDENTIALS, $dto->getGrantType());
        self::assertSame('openid profile', $dto->getScope());
    }

    public function testClientCredentialsDoesNotRequireUsernamePasswordOrRefreshToken(): void
    {
        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            grantType: OidcGrantType::CLIENT_CREDENTIALS,
        );

        self::assertSame(
            [
                'grant_type' => 'client_credentials',
                'client_id' => 'backend',
                'client_secret' => 'secret',
            ],
            $dto->toFormParams(),
        );
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

    public function testPasswordGrantRejectsRefreshToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OIDC password grant must not include a refresh token.');

        new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
            refreshToken: 'refresh-token',
            grantType: OidcGrantType::PASSWORD,
        );
    }

    public function testForRefreshTokenBuildsExpectedFormParams(): void
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
        self::assertSame(OidcGrantType::REFRESH_TOKEN, $dto->getGrantType());
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

    public function testRefreshTokenRejectsUsernameAndPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OIDC refresh token grant must not include username or password.');

        new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
            refreshToken: 'refresh-token',
            grantType: OidcGrantType::REFRESH_TOKEN,
        );
    }

    public function testClientCredentialsRejectsUserCredentialsAndRefreshToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'OIDC client credentials grant must not include username, password, or refresh token.',
        );

        new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
            refreshToken: 'refresh-token',
            grantType: OidcGrantType::CLIENT_CREDENTIALS,
        );
    }

    public function testScopeIsIncludedWhenProvidedForPasswordGrant(): void
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

    public function testDebugInfoRedactsClientSecretAndRefreshToken(): void
    {
        $dto = OidcTokenRequestDto::forRefreshToken(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'top-secret-client-secret',
            refreshToken: 'top-secret-refresh-token',
        );

        ob_start();
        var_dump($dto);
        $debugOutput = (string) ob_get_clean();

        self::assertStringContainsString('[redacted]', $debugOutput);
        self::assertStringNotContainsString('top-secret-client-secret', $debugOutput);
        self::assertStringNotContainsString('top-secret-refresh-token', $debugOutput);
    }

    public function testDebugInfoRedactsPassword(): void
    {
        $dto = OidcTokenRequestDto::forPasswordGrant(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'top-secret-client-secret',
            username: 'oleg@example.com',
            password: 'SuperSecretPassword!2026',
        );

        ob_start();
        var_dump($dto);
        $debugOutput = (string) ob_get_clean();

        self::assertStringContainsString('[redacted]', $debugOutput);
        self::assertStringNotContainsString('top-secret-client-secret', $debugOutput);
        self::assertStringNotContainsString('SuperSecretPassword!2026', $debugOutput);
    }
}
