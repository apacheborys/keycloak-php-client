<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Token;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Assert\Assert;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

final readonly class ClientCredentialsTokenProvider implements AccessTokenProviderInterface
{
    private const int DEFAULT_CACHE_SAFETY_SKEW = 1;

    public function __construct(
        private OidcInteractionHttpClientInterface $oidcInteractionHttpClient,
        private KeycloakClientConfig $config,
        private ?CacheItemPoolInterface $cache = null,
        private ?string $scope = null,
        private int $cacheSafetySkew = self::DEFAULT_CACHE_SAFETY_SKEW,
    ) {
        if ($this->scope !== null) {
            Assert::that($this->scope)->notEmpty();
        }

        Assert::that($this->cacheSafetySkew)->integer()->greaterOrEqualThan(0);
    }

    #[\Override]
    public function getAccessToken(): JsonWebToken
    {
        $cachedToken = $this->readAccessTokenFromCache();
        if ($cachedToken instanceof JsonWebToken) {
            return $cachedToken;
        }

        $response = $this->oidcInteractionHttpClient->requestToken(
            dto: OidcTokenRequestDto::forClientCredentials(
                realm: $this->config->getClientRealm(),
                clientId: $this->config->getClientId(),
                clientSecret: $this->config->getClientSecret(),
                scope: $this->scope,
            ),
        );

        $this->storeAccessTokenInCache($response);

        return $response->getAccessToken();
    }

    private function readAccessTokenFromCache(): ?JsonWebToken
    {
        if ($this->cache === null) {
            return null;
        }

        $cacheItem = $this->cache->getItem(key: $this->accessTokenCacheKey());
        if (!$cacheItem->isHit()) {
            return null;
        }

        $cachedToken = $cacheItem->get();
        if (!is_string(value: $cachedToken) || $cachedToken === '') {
            return null;
        }

        try {
            return JsonWebToken::fromRawToken(rawToken: $cachedToken);
        } catch (Throwable) {
            return null;
        }
    }

    private function storeAccessTokenInCache(OidcTokenResponseDto $response): void
    {
        if ($this->cache === null) {
            return;
        }

        $cacheItem = $this->cache->getItem(key: $this->accessTokenCacheKey());
        $cacheItem->set(value: $response->getAccessToken()->getRawToken());
        $cacheItem->expiresAfter(time: max(0, $response->getExpiresIn() - $this->cacheSafetySkew));
        $this->cache->save(item: $cacheItem);
    }

    private function accessTokenCacheKey(): string
    {
        $parts = [
            $this->config->getBaseUrl(),
            $this->config->getClientRealm(),
            $this->config->getClientId(),
        ];

        if ($this->scope !== null) {
            $parts[] = $this->scope;
        }

        return 'keycloak.access_token.' . sha1(string: implode('|', $parts));
    }
}
