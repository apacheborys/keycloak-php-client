<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Exception\CreateUserException;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    private const string CLIENT_NAME = 'Keycloak PHP Client';

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

    public function getUser(array $payload): array
    {
        throw new LogicException(message: 'Not implemented');
    }

    public function createUser(CreateUserDto $dto): void
    {
        $token = $this->getAccessToken(realm: $dto->getRealm());

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/realms/' . $dto->getRealm() . '/users';

        $payload = json_encode(value: $dto->toArray());

        $request = $this->requestFactory->createRequest(method: 'POST', uri: $endpoint)
            ->withHeader(name: 'Authorization', value: 'Bearer ' . $token->getRawToken())
            ->withHeader(name: 'Content-Type', value: 'application/json')
            ->withHeader(name: 'User-Agent', value: self::CLIENT_NAME)
            ->withBody(body: $this->streamFactory->createStream(content: $payload));

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 201) {
            return;
        }

        throw new CreateUserException(message: (string) $response->getBody());
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

    private function getAccessToken(string $realm): JsonWebToken
    {
        $cacheKey = 'keycloak.access_token.' . sha1(string: $this->baseUrl . '|' . $realm . '|' . $this->clientId);

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedToken = $cacheItem->get();

                if (is_string(value: $cachedToken) && $cachedToken !== '') {
                    return JsonWebToken::fromRawToken(rawToken: $cachedToken);
                }
            }
        }

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') .
                        '/realms/' . $realm . '/protocol/openid-connect/token';

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
            ->withHeader(name: 'User-Agent', value: self::CLIENT_NAME)
            ->withBody(body: $this->streamFactory->createStream(content: $payload));

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak token request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = json_decode(json: $body, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array(value: $data)) {
            throw new RuntimeException(message: 'Keycloak token response is not valid JSON.');
        }

        $dto = RequestAccessDto::fromArray(data: $data);
        $accessToken = JsonWebToken::fromRawToken(rawToken: $dto->getAccessToken());

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $accessToken->getRawToken());
            $cacheItem->expiresAfter(time: $dto->getExpiresIn() - 1);

            $this->cache->save(item: $cacheItem);
        }

        return $accessToken;
    }
}
