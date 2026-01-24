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
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    private const string CLIENT_NAME = 'Keycloak PHP Client';

    private const int REALM_LIST_TTL = 3600;

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

    #[\Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        $token = $this->getAccessToken();

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/realms/' . $dto->getRealm() . '/users';
        $query = $this->buildUsersQuery(dto: $dto);

        if ($query !== '') {
            $endpoint .= '?' . $query;
        }

        $request = $this->requestFactory->createRequest(method: 'GET', uri: $endpoint)
            ->withHeader(name: 'Authorization', value: 'Bearer ' . $token->getRawToken())
            ->withHeader(name: 'User-Agent', value: self::CLIENT_NAME);

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak users request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = json_decode(json: $body, associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::that(value: $data)->isArray();

        /** @var array<int, array<string, mixed>> $data */

        $users = [];
        foreach ($data as $userData) {
            Assert::that(value: $userData)->isArray();
            $users[] = KeycloakUser::fromArray(data: $userData);
        }

        return $users;
    }

    #[\Override]
    public function createUser(CreateUserDto $dto): void
    {
        $token = $this->getAccessToken();

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/realms/' . $dto->getRealm() . '/users';

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

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

    #[\Override]
    public function updateUser(string $userId, array $payload): array
    {
        throw new LogicException(message: 'HTTP updateUser is not implemented yet.');
    }

    #[\Override]
    public function deleteUser(string $userId): void
    {
        throw new LogicException(message: 'HTTP deleteUser is not implemented yet.');
    }

    #[\Override]
    public function createRealm(array $payload): array
    {
        throw new LogicException(message: 'HTTP createRealm is not implemented yet.');
    }

    #[\Override]
    public function getRoles(): array
    {
        throw new LogicException('HTTP getRole is not implemented yet.');
    }

    #[\Override]
    public function deleteRole(string $role): void
    {
        throw new LogicException(message: 'HTTP deleteRole is not implemented yet.');
    }

    #[\Override]
    public function getJwks(string $realm): array
    {
        throw new LogicException(message: 'HTTP getJwks is not implemented yet.');
    }

    #[\Override()]
    public function getAvailableRealms(): array
    {
        $cacheKey = 'keycloak.realm_list.' . sha1(string: $this->baseUrl . '|' . $this->clientId);

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedRealms = $cacheItem->get();

                if (is_string(value: $cachedRealms) && $cachedRealms !== '') {
                    $data = json_decode(json: $cachedRealms, associative: true, flags: JSON_THROW_ON_ERROR);
                    Assert::that(value: $data)->isArray();

                    /** @var array<int, array<string, mixed>> $data */

                    $realms = [];
                    foreach ($data as $realmData) {
                        Assert::that(value: $realmData)->isArray();
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

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/realms?' .
                    http_build_query(data: $parameters, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

        $request = $this->requestFactory->createRequest(method: 'GET', uri: $endpoint)
            ->withHeader(name: 'Authorization', value: 'Bearer ' . $token->getRawToken())
            ->withHeader(name: 'User-Agent', value: self::CLIENT_NAME);

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

        $data = json_decode(json: $body, associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::that(value: $data)->isArray();

        /** @var array<int, array<string, mixed>> $data */

        $realms = [];
        foreach ($data as $realmData) {
            Assert::that(value: $realmData)->isArray();
            $realms[] = KeycloakRealm::fromArray(data: $realmData);
        }

        return $realms;
    }

    #[\Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $token = $this->getAccessToken();

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/realms/' . $dto->getRealm() .
                    '/users/' . $dto->getUser()->getId() . '/reset-password';

        /** @var string $payload */
        $payload = json_encode(
            value: [
                'type' => $dto->getType()->value(),
                'temporary' => $dto->isTemporary(),
                'value' => $dto->getValue(),
            ],
            flags: JSON_THROW_ON_ERROR,
        );

        $request = $this->requestFactory->createRequest(method: 'POST', uri: $endpoint)
            ->withHeader(name: 'Authorization', value: 'Bearer ' . $token->getRawToken())
            ->withHeader(name: 'Content-Type', value: 'application/json')
            ->withHeader(name: 'User-Agent', value: self::CLIENT_NAME)
            ->withBody(body: $this->streamFactory->createStream(content: $payload));

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

        $endpoint = rtrim(string: $this->baseUrl, characters: '/') .
                        '/realms/' . $this->clientRealm . '/protocol/openid-connect/token';

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
