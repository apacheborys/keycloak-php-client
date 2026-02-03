<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Exception\CreateUserException;
use Assert\Assert;
use LogicException;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    private const string CLIENT_NAME = 'Keycloak PHP Client';

    public const int REALM_LIST_TTL = 3600;

    public function __construct(
        private string $baseUrl,
        private string $clientRealm,
        private string $clientId,
        private string $clientSecret,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ?CacheItemPoolInterface $cache = null,
        private int $realmListTtl = self::REALM_LIST_TTL,
    ) {
    }

    #[Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        $token = $this->getAccessToken();

        $query = $this->buildUsersQuery(dto: $dto);
        $endpoint = $this->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/users', query: $query);
        $request = $this->createRequest(
            method: 'GET',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak users request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->decodeJson(body: $body);

        /** @var array<int, array<string, mixed>> $data */

        $users = [];
        foreach ($data as $userData) {
            Assert::that($userData)->isArray();
            $users[] = KeycloakUser::fromArray(data: $userData);
        }

        return $users;
    }

    #[Override]
    public function createUser(CreateUserDto $dto): void
    {
        $token = $this->getAccessToken();

        $endpoint = $this->buildEndpoint(path: '/admin/realms/' . $dto->getProfile()->getRealm() . '/users');

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

        $request = $this->createRequest(
            method: 'POST',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 201) {
            return;
        }

        throw new CreateUserException(message: (string) $response->getBody());
    }

    #[Override]
    public function updateUser(string $userId, array $payload): array
    {
        throw new LogicException(message: 'HTTP updateUser is not implemented yet.');
    }

    #[Override]
    public function deleteUser(string $userId): void
    {
        throw new LogicException(message: 'HTTP deleteUser is not implemented yet.');
    }

    #[Override]
    public function createRealm(array $payload): array
    {
        throw new LogicException(message: 'HTTP createRealm is not implemented yet.');
    }

    #[Override]
    public function getRoles(): array
    {
        throw new LogicException('HTTP getRoles is not implemented yet.');
    }

    #[Override]
    public function deleteRole(string $role): void
    {
        throw new LogicException(message: 'HTTP deleteRole is not implemented yet.');
    }

    #[Override]
    public function getJwks(string $realm): array
    {
        throw new LogicException(message: 'HTTP getJwks is not implemented yet.');
    }

    #[Override]
    public function getAvailableRealms(): array
    {
        $cacheKey = 'keycloak.realm_list.' . sha1(string: $this->baseUrl . '|' . $this->clientId);

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedRealms = $cacheItem->get();

                if (is_string(value: $cachedRealms) && $cachedRealms !== '') {
                    $data = $this->decodeJson(body: $cachedRealms);

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

        $token = $this->getAccessToken();

        $parameters = [
            'briefRepresentation' => 'true',
        ];

        $endpoint = $this->buildEndpoint(
            path: '/admin/realms',
            query: http_build_query(
                data: $parameters,
                arg_separator: '&',
                encoding_type: PHP_QUERY_RFC3986
            ),
        );

        $request = $this->createRequest(
            method: 'GET',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak available realms request failed with status %d: %s', $statusCode, $body)
            );
        }

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $body);
            $cacheItem->expiresAfter(time: $this->realmListTtl);

            $this->cache->save(item: $cacheItem);
        }

        $data = $this->decodeJson(body: $body);

        /** @var array<int, array<string, mixed>> $data */

        $realms = [];
        foreach ($data as $realmData) {
            Assert::that($realmData)->isArray();
            $realms[] = KeycloakRealm::fromArray(data: $realmData);
        }

        return $realms;
    }

    #[Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $token = $this->getAccessToken();

        $endpoint = $this->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/users/' . $dto->getUser()->getId() . '/reset-password'
        );

        /** @var string $payload */
        $payload = json_encode(
            value: [
                'type' => $dto->getType()->value(),
                'temporary' => $dto->isTemporary(),
                'value' => $dto->getValue(),
            ],
            flags: JSON_THROW_ON_ERROR,
        );

        $request = $this->createRequest(
            method: 'PUT',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 204) {
            return;
        }

        throw new LogicException("Can't set password, response: " . $response->getBody()->getContents());
    }

    private function getAccessToken(): JsonWebToken
    {
        $cacheKey = 'keycloak.access_token.' .
                    sha1(string: $this->baseUrl . '|' . $this->clientRealm . '|' . $this->clientId);

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedToken = $cacheItem->get();

                if (is_string(value: $cachedToken) && $cachedToken !== '') {
                    return JsonWebToken::fromRawToken(rawToken: $cachedToken);
                }
            }
        }

        $endpoint = $this->buildEndpoint(
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

        $request = $this->createRequest(
            method: 'POST',
            endpoint: $endpoint,
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: $payload,
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak token request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->decodeJson(body: $body);

        $dto = RequestAccessDto::fromArray(data: $data);
        $accessToken = JsonWebToken::fromRawToken(rawToken: $dto->getAccessToken());

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $accessToken->getRawToken());
            $cacheItem->expiresAfter(time: max(0, $dto->getExpiresIn() - 1));

            $this->cache->save(item: $cacheItem);
        }

        return $accessToken;
    }

    private function buildEndpoint(string $path, string $query = ''): string
    {
        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/' . ltrim($path, '/');

        if ($query === '') {
            return $endpoint;
        }

        return $endpoint . '?' . $query;
    }

    /**
     * @param array<string, string> $headers
     */
    private function createRequest(
        string $method,
        string $endpoint,
        array $headers = [],
        ?string $body = null,
    ): RequestInterface {
        $request = $this->requestFactory
            ->createRequest($method, $endpoint)
            ->withHeader('User-Agent', self::CLIENT_NAME);

        foreach ($headers as $headerName => $headerValue) {
            $request = $request->withHeader($headerName, $headerValue);
        }

        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(string $body): array
    {
        $data = json_decode(json: $body, associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::that($data)->isArray();

        /** @var array<mixed> $data */

        return $data;
    }

    private function buildUsersQuery(SearchUsersDto $dto): string
    {
        $queryParts = [];

        $params = $dto->getQueryParameters();
        if ($params !== []) {
            $queryParts[] = http_build_query(
                data: $params,
                numeric_prefix: '',
                arg_separator: '&',
                encoding_type: PHP_QUERY_RFC3986
            );
        }

        foreach ($dto->getCustomAttributes() as $attributeName => $customAttribute) {
            $queryParts[] = 'q=' . rawurlencode((string) $attributeName)
                . ':' . rawurlencode((string) $customAttribute);
        }

        return implode('&', $queryParts);
    }
}
