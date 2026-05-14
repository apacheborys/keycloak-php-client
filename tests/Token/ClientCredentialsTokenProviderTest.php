<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Token;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\Token\ClientCredentialsTokenProvider;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class ClientCredentialsTokenProviderTest extends TestCase
{
    public function testCacheMissRequestsTokenReturnsAccessTokenAndStoresRawTokenWithTtl(): void
    {
        $rawToken = JwtTestFactory::buildJwtToken();
        $expectedCacheKey = $this->buildCacheKey(scope: 'openid profile');
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects(self::once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItem
            ->expects(self::once())
            ->method('set')
            ->with($rawToken)
            ->willReturnSelf();
        $cacheItem
            ->expects(self::once())
            ->method('expiresAfter')
            ->with(3599)
            ->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(self::exactly(2))
            ->method('getItem')
            ->with($expectedCacheKey)
            ->willReturn($cacheItem);
        $cache
            ->expects(self::once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $oidcInteraction = $this->createMock(OidcInteractionHttpClientInterface::class);
        $oidcInteraction
            ->expects(self::once())
            ->method('requestToken')
            ->with(self::callback(function (OidcTokenRequestDto $dto): bool {
                return $dto->toFormParams() === [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'backend',
                    'client_secret' => 'secret',
                    'scope' => 'openid profile',
                ];
            }))
            ->willReturn($this->buildTokenResponseDto(rawToken: $rawToken, expiresIn: 3600));

        $provider = new ClientCredentialsTokenProvider(
            oidcInteractionHttpClient: $oidcInteraction,
            config: $this->buildConfig(),
            cache: $cache,
            scope: 'openid profile',
        );

        $accessToken = $provider->getAccessToken();

        self::assertInstanceOf(JsonWebToken::class, $accessToken);
        self::assertSame($rawToken, $accessToken->getRawToken());
    }

    public function testCacheHitReturnsCachedJsonWebTokenWithoutRequestingNewToken(): void
    {
        $rawToken = JwtTestFactory::buildJwtToken();
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects(self::once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->expects(self::once())
            ->method('get')
            ->willReturn($rawToken);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(self::once())
            ->method('getItem')
            ->with($this->buildCacheKey())
            ->willReturn($cacheItem);
        $cache
            ->expects(self::never())
            ->method('save');

        $oidcInteraction = $this->createMock(OidcInteractionHttpClientInterface::class);
        $oidcInteraction
            ->expects(self::never())
            ->method('requestToken');

        $provider = new ClientCredentialsTokenProvider(
            oidcInteractionHttpClient: $oidcInteraction,
            config: $this->buildConfig(),
            cache: $cache,
        );

        $accessToken = $provider->getAccessToken();

        self::assertSame($rawToken, $accessToken->getRawToken());
    }

    public function testMalformedCachedTokenIsIgnoredAndReplaced(): void
    {
        $rawToken = JwtTestFactory::buildJwtToken();
        $expectedCacheKey = $this->buildCacheKey();
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects(self::once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->expects(self::once())
            ->method('get')
            ->willReturn('not-a-jwt');
        $cacheItem
            ->expects(self::once())
            ->method('set')
            ->with($rawToken)
            ->willReturnSelf();
        $cacheItem
            ->expects(self::once())
            ->method('expiresAfter')
            ->with(3599)
            ->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(self::exactly(2))
            ->method('getItem')
            ->with($expectedCacheKey)
            ->willReturn($cacheItem);
        $cache
            ->expects(self::once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $oidcInteraction = $this->createMock(OidcInteractionHttpClientInterface::class);
        $oidcInteraction
            ->expects(self::once())
            ->method('requestToken')
            ->willReturn($this->buildTokenResponseDto(rawToken: $rawToken, expiresIn: 3600));

        $provider = new ClientCredentialsTokenProvider(
            oidcInteractionHttpClient: $oidcInteraction,
            config: $this->buildConfig(),
            cache: $cache,
        );

        $accessToken = $provider->getAccessToken();

        self::assertSame($rawToken, $accessToken->getRawToken());
    }

    public function testExpiredCachedTokenIsIgnoredAndReplaced(): void
    {
        $rawToken = JwtTestFactory::buildJwtToken();
        $expectedCacheKey = $this->buildCacheKey();
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects(self::once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItem
            ->expects(self::never())
            ->method('get');
        $cacheItem
            ->expects(self::once())
            ->method('set')
            ->with($rawToken)
            ->willReturnSelf();
        $cacheItem
            ->expects(self::once())
            ->method('expiresAfter')
            ->with(3599)
            ->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects(self::exactly(2))
            ->method('getItem')
            ->with($expectedCacheKey)
            ->willReturn($cacheItem);
        $cache
            ->expects(self::once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $oidcInteraction = $this->createMock(OidcInteractionHttpClientInterface::class);
        $oidcInteraction
            ->expects(self::once())
            ->method('requestToken')
            ->willReturn($this->buildTokenResponseDto(rawToken: $rawToken, expiresIn: 3600));

        $provider = new ClientCredentialsTokenProvider(
            oidcInteractionHttpClient: $oidcInteraction,
            config: $this->buildConfig(),
            cache: $cache,
        );

        $accessToken = $provider->getAccessToken();

        self::assertSame($rawToken, $accessToken->getRawToken());
    }

    private function buildConfig(): KeycloakClientConfig
    {
        return new KeycloakClientConfig(
            baseUrl: 'https://keycloak.example',
            clientRealm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
        );
    }

    private function buildTokenResponseDto(string $rawToken, int $expiresIn): OidcTokenResponseDto
    {
        return new OidcTokenResponseDto(
            accessToken: JsonWebToken::fromRawToken($rawToken),
            expiresIn: $expiresIn,
            refreshExpiresIn: 0,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'openid profile',
        );
    }

    private function buildCacheKey(?string $scope = null): string
    {
        $parts = [
            'https://keycloak.example',
            'master',
            'backend',
        ];

        if ($scope !== null) {
            $parts[] = $scope;
        }

        return 'keycloak.access_token.' . sha1(implode('|', $parts));
    }
}
