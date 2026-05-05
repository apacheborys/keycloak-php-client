<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

final readonly class AccessTokenProvider
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private string $clientRealm,
        private string $clientId,
        private string $clientSecret,
        private ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function getAccessToken(): JsonWebToken
    {
        $cacheKey = 'keycloak.access_token.'
            . sha1(string: $this->httpCore->getBaseUrl() . '|' . $this->clientRealm . '|' . $this->clientId);

        $cache = $this->cache;
        if ($cache !== null) {
            $cacheItem = $cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedToken = $cacheItem->get();

                if (is_string(value: $cachedToken) && $cachedToken !== '') {
                    return JsonWebToken::fromRawToken(rawToken: $cachedToken);
                }
            }
        }

        $endpoint = $this->httpCore->buildEndpoint(
            path: '/realms/' . $this->clientRealm . '/protocol/openid-connect/token'
        );

        $payload = http_build_query(
            data: [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
            numeric_prefix: '',
            arg_separator: '&',
            encoding_type: PHP_QUERY_RFC3986
        );

        $request = $this->httpCore->createRequest(
            method: 'POST',
            endpoint: $endpoint,
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak token request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);
        $dto = OidcTokenResponseDto::fromArray(data: $data);

        if ($cache !== null) {
            $cacheItem = $cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $dto->getAccessToken()->getRawToken());
            $cacheItem->expiresAfter(time: max(0, $dto->getExpiresIn() - 1));
            $cache->save(item: $cacheItem);
        }

        return $dto->getAccessToken();
    }
}
