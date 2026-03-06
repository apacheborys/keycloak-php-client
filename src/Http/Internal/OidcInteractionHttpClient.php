<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Assert\Assert;
use RuntimeException;

final readonly class OidcInteractionHttpClient implements OidcInteractionHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProvider $accessTokenProvider,
        private string $clientId,
        private int $realmListTtl,
        private int $openIdConfigurationTtl,
        private int $jwkByKidTtl,
    ) {
    }

    #[\Override]
    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto
    {
        $cacheKey = 'keycloak.openid_configuration.' . sha1(string: $this->httpCore->getBaseUrl() . '|' . $realm);
        $cache = $this->httpCore->getCache();

        if ($allowToUseCache && $cache !== null) {
            $cacheItem = $cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedValue = $cacheItem->get();

                if (is_string(value: $cachedValue) && $cachedValue !== '') {
                    $cachedData = $this->httpCore->decodeJson(body: $cachedValue);

                    return OpenIdConfigurationDto::fromArray(data: $cachedData);
                }
            }
        }

        $endpoint = $this->httpCore->buildEndpoint(path: '/realms/' . $realm . '/.well-known/openid-configuration');
        $request = $this->httpCore->createRequest(method: 'GET', endpoint: $endpoint);

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak OpenID configuration request failed with status %d: %s', $statusCode, $body)
            );
        }

        if ($cache !== null) {
            $cacheItem = $cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $body);
            $cacheItem->expiresAfter(time: $this->openIdConfigurationTtl);
            $cache->save(item: $cacheItem);
        }

        $data = $this->httpCore->decodeJson(body: $body);

        return OpenIdConfigurationDto::fromArray(data: $data);
    }

    #[\Override]
    public function getJwk(
        string $realm,
        string $kid,
        string $jwksUri,
        bool $allowToUseCache = true,
    ): ?JwkDto {
        if ($allowToUseCache) {
            $cachedJwk = $this->getCachedJwk(realm: $realm, kid: $kid);

            if ($cachedJwk instanceof JwkDto) {
                return $cachedJwk;
            }
        }

        $jwks = $this->getJwks(
            realm: $realm,
            jwksUri: $jwksUri,
        );

        return $jwks->findByKid(kid: $kid);
    }

    #[\Override]
    public function getJwks(string $realm, string $jwksUri): JwksDto
    {
        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $jwksUri,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak JWKS request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);
        $jwks = JwksDto::fromArray(data: $data);

        $cache = $this->httpCore->getCache();
        if ($cache !== null) {
            foreach ($jwks->getKeys() as $key) {
                $keyCacheItem = $cache->getItem(
                    key: 'keycloak.jwk_by_kid.'
                        . sha1(string: $this->httpCore->getBaseUrl() . '|' . $realm . '|' . $key->getKid())
                );
                /** @var string $serialized */
                $serialized = json_encode(value: $key->toArray(), flags: JSON_THROW_ON_ERROR);
                $keyCacheItem->set(value: $serialized);
                $keyCacheItem->expiresAfter(time: $this->jwkByKidTtl);
                $cache->save(item: $keyCacheItem);
            }
        }

        return $jwks;
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[\Override]
    public function getAvailableRealms(): array
    {
        $cacheKey = 'keycloak.realm_list.' . sha1(string: $this->httpCore->getBaseUrl() . '|' . $this->clientId);
        $cache = $this->httpCore->getCache();

        if ($cache !== null) {
            $cacheItem = $cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedRealms = $cacheItem->get();

                if (is_string(value: $cachedRealms) && $cachedRealms !== '') {
                    $data = $this->httpCore->decodeJson(body: $cachedRealms);

                    /** @var array<int, array<string, mixed>> $data */
                    $realms = [];
                    foreach ($data as $realmData) {
                        Assert::that($realmData)->isArray();
                        $realms[] = KeycloakRealm::fromArray(data: $realmData);
                    }

                    return $realms;
                }
            }
        }

        $token = $this->accessTokenProvider->getAccessToken();
        $parameters = [
            'briefRepresentation' => 'true',
        ];

        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms',
            query: http_build_query(
                data: $parameters,
                arg_separator: '&',
                encoding_type: PHP_QUERY_RFC3986
            ),
        );

        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak available realms request failed with status %d: %s', $statusCode, $body)
            );
        }

        if ($cache !== null) {
            $cacheItem = $cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $body);
            $cacheItem->expiresAfter(time: $this->realmListTtl);
            $cache->save(item: $cacheItem);
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, array<string, mixed>> $data */
        $realms = [];
        foreach ($data as $realmData) {
            Assert::that($realmData)->isArray();
            $realms[] = KeycloakRealm::fromArray(data: $realmData);
        }

        return $realms;
    }

    #[\Override]
    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->requestToken(dto: $dto);
    }

    #[\Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->requestToken(dto: $dto);
    }

    private function getCachedJwk(string $realm, string $kid): ?JwkDto
    {
        $cache = $this->httpCore->getCache();
        if ($cache === null) {
            return null;
        }

        $cacheItem = $cache->getItem(
            key: 'keycloak.jwk_by_kid.' . sha1(string: $this->httpCore->getBaseUrl() . '|' . $realm . '|' . $kid)
        );

        if (!$cacheItem->isHit()) {
            return null;
        }

        $cachedValue = $cacheItem->get();
        if (!is_string(value: $cachedValue) || $cachedValue === '') {
            return null;
        }

        $cachedData = $this->httpCore->decodeJson(body: $cachedValue);

        return JwkDto::fromArray(data: $cachedData);
    }

    private function requestToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/realms/' . $dto->getRealm() . '/protocol/openid-connect/token'
        );

        $payload = http_build_query(
            data: $dto->toFormParams(),
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
                message: sprintf(
                    'Keycloak %s request failed with status %d: %s',
                    $dto->getGrantType()->value,
                    $statusCode,
                    $body
                )
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        return OidcTokenResponseDto::fromArray(data: $data);
    }
}
