<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\RequestAccessResponseDTO;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private CacheItemPoolInterface|null $cache = null,
    ) {
    }

    public function createUser(array $payload): array
    {
        throw new LogicException(message: 'HTTP createUser is not implemented yet.');
    }

    public function updateUser(string $userId, array $payload): array
    {
        throw new LogicException(message: 'HTTP updateUser is not implemented yet.');
    }

    public function deleteUser(string $userId): void
    {
        throw new LogicException(message: 'HTTP deleteUser is not implemented yet.');
    }

    public function createRealm(array $payload): array
    {
        throw new LogicException(message: 'HTTP createRealm is not implemented yet.');
    }

    public function getRoles(): array
    {
        throw new LogicException('HTTP getRole is not implemented yet.');
    }

    public function deleteRole(string $role): void
    {
        throw new LogicException(message: 'HTTP deleteRole is not implemented yet.');
    }

    public function getJwks(string $realm): array
    {
        throw new LogicException(message: 'HTTP getJwks is not implemented yet.');
    }

    private function getAccessToken(string $realm): string
    {
        $cacheKey = 'keycloak.access_token.' . sha1(string: $this->baseUrl . '|' . $realm . '|' . $this->clientId . '|' . $this->clientSecret);

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedToken = $cacheItem->get();

                if (is_string(value: $cachedToken) && $cachedToken !== '') {
                    return $cachedToken;
                }
            }
        }

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/realms/' . $realm . '/protocol/openid-connect/token';
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

        $request = $this->requestFactory->createRequest(method: 'POST', uri: $endpoint)
            ->withHeader(name: 'Content-Type', value: 'application/x-www-form-urlencoded')
            ->withBody(body: $this->streamFactory->createStream(content: $payload));

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak token request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = json_decode(json: $body, associative: true);

        if (!is_array(value: $data)) {
            throw new RuntimeException(message: 'Keycloak token response is not valid JSON.');
        }

        $dto = RequestAccessResponseDTO::fromArray(data: $data);
        $accessToken = $dto->getAccessToken();

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $accessToken);

            $expiresIn = $dto->getExpiresIn();
            if ($expiresIn > 0) {
                $ttl = max(value: 1, values: $expiresIn - 5);
                $cacheItem->expiresAfter(time: $ttl);
            }

            $this->cache->save(item: $cacheItem);
        }

        return $accessToken;
    }
}
