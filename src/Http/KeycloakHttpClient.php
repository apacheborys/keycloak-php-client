<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
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
    public const int OPENID_CONFIGURATION_TTL = 86400;
    public const int JWK_BY_KID_TTL = 86400;

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
        private int $openIdConfigurationTtl = self::OPENID_CONFIGURATION_TTL,
        private int $jwkByKidTtl = self::JWK_BY_KID_TTL,
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
    public function updateUser(UpdateUserDto $dto): void
    {
        $token = $this->getAccessToken();

        $endpoint = $this->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/users/' . $dto->getUserId()->toString()
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

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

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak update user failed with status %d: %s', $statusCode, $body)
        );
    }

    #[Override]
    public function deleteUser(DeleteUserDto $dto): void
    {
        $token = $this->getAccessToken();

        $endpoint = $this->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/users/' . $dto->getUserId()->toString()
        );

        $request = $this->createRequest(
            method: 'DELETE',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak delete user failed with status %d: %s', $statusCode, $body)
        );
    }

    #[Override]
    public function createRealm(array $payload): array
    {
        throw new LogicException(message: 'HTTP createRealm is not implemented yet.');
    }

    #[Override]
    public function getRoles(GetRolesDto $dto): array
    {
        $token = $this->getAccessToken();
        $endpoint = $this->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/roles');

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
                message: sprintf('Keycloak get roles failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->decodeJson(body: $body);

        /** @var array<int, mixed> $data */
        $roles = [];
        foreach ($data as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $roles[] = RoleDto::fromArray(data: $item);
        }

        return $roles;
    }

    #[Override]
    public function getAvailableUserRoles(GetUserAvailableRolesDto $dto): array
    {
        $token = $this->getAccessToken();
        $endpoint = $this->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm()
                . '/users/' . $dto->getUserId()->toString()
                . '/role-mappings/realm/available'
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
                message: sprintf('Keycloak get available user roles failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->decodeJson(body: $body);

        /** @var array<int, mixed> $data */
        $roles = [];
        foreach ($data as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $roles[] = RoleDto::fromArray(data: $item);
        }

        return $roles;
    }

    #[Override]
    public function createRole(CreateRoleDto $dto): void
    {
        $token = $this->getAccessToken();
        $endpoint = $this->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/roles');

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

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        // If role was created in parallel by another process, Keycloak may return 409.
        if ($statusCode === 409) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak create role failed with status %d: %s', $statusCode, $body)
        );
    }

    #[Override]
    public function deleteRole(DeleteRoleDto $dto): void
    {
        $token = $this->getAccessToken();
        $endpoint = $this->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/roles/' . rawurlencode($dto->getRoleName())
        );

        $request = $this->createRequest(
            method: 'DELETE',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak delete role failed with status %d: %s', $statusCode, $body)
        );
    }

    #[Override]
    public function assignRolesToUser(AssignUserRolesDto $dto): void
    {
        $this->changeUserRoleMappings(
            dto: $dto,
            method: 'POST',
        );
    }

    #[Override]
    public function unassignRolesFromUser(AssignUserRolesDto $dto): void
    {
        $this->changeUserRoleMappings(
            dto: $dto,
            method: 'DELETE',
        );
    }

    #[Override]
    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto
    {
        $cacheKey = 'keycloak.openid_configuration.' . sha1(string: $this->baseUrl . '|' . $realm);

        if ($allowToUseCache && $this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);

            if ($cacheItem->isHit()) {
                $cachedValue = $cacheItem->get();

                if (is_string(value: $cachedValue) && $cachedValue !== '') {
                    $cachedData = $this->decodeJson(body: $cachedValue);

                    return OpenIdConfigurationDto::fromArray(data: $cachedData);
                }
            }
        }

        $endpoint = $this->buildEndpoint(path: '/realms/' . $realm . '/.well-known/openid-configuration');
        $request = $this->createRequest(method: 'GET', endpoint: $endpoint);

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak OpenID configuration request failed with status %d: %s', $statusCode, $body)
            );
        }

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $body);
            $cacheItem->expiresAfter(time: $this->openIdConfigurationTtl);
            $this->cache->save(item: $cacheItem);
        }

        $data = $this->decodeJson(body: $body);

        return OpenIdConfigurationDto::fromArray(data: $data);
    }

    #[Override]
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

    private function getCachedJwk(string $realm, string $kid): ?JwkDto
    {
        if ($this->cache === null) {
            return null;
        }

        $cacheItem = $this->cache->getItem(
            key: 'keycloak.jwk_by_kid.' . sha1(string: $this->baseUrl . '|' . $realm . '|' . $kid)
        );

        if (!$cacheItem->isHit()) {
            return null;
        }

        $cachedValue = $cacheItem->get();
        if (!is_string(value: $cachedValue) || $cachedValue === '') {
            return null;
        }

        $cachedData = $this->decodeJson(body: $cachedValue);

        return JwkDto::fromArray(data: $cachedData);
    }

    #[Override]
    public function getJwks(string $realm, string $jwksUri): JwksDto
    {
        $request = $this->createRequest(
            method: 'GET',
            endpoint: $jwksUri,
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak JWKS request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->decodeJson(body: $body);

        $jwks = JwksDto::fromArray(data: $data);

        if ($this->cache !== null) {
            foreach ($jwks->getKeys() as $key) {
                $keyCacheItem = $this->cache->getItem(
                    key: 'keycloak.jwk_by_kid.' . sha1(string: $this->baseUrl . '|' . $realm . '|' . $key->getKid())
                );
                /** @var string $serialized */
                $serialized = json_encode(value: $key->toArray(), flags: JSON_THROW_ON_ERROR);
                $keyCacheItem->set(value: $serialized);
                $keyCacheItem->expiresAfter(time: $this->jwkByKidTtl);
                $this->cache->save(item: $keyCacheItem);
            }
        }

        return $jwks;
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

    #[Override]
    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->requestToken(dto: $dto);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->requestToken(dto: $dto);
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

        $dto = OidcTokenResponseDto::fromArray(data: $data);

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem(key: $cacheKey);
            $cacheItem->set(value: $dto->getAccessToken()->getRawToken());
            $cacheItem->expiresAfter(time: max(0, $dto->getExpiresIn() - 1));

            $this->cache->save(item: $cacheItem);
        }

        return $dto->getAccessToken();
    }

    private function requestToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        $endpoint = $this->buildEndpoint(
            path: '/realms/' . $dto->getRealm() . '/protocol/openid-connect/token'
        );

        $payload = http_build_query(
            data: $dto->toFormParams(),
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
                message: sprintf(
                    'Keycloak %s request failed with status %d: %s',
                    $dto->getGrantType()->value,
                    $statusCode,
                    $body
                )
            );
        }

        $data = $this->decodeJson(body: $body);

        return OidcTokenResponseDto::fromArray(data: $data);
    }

    private function changeUserRoleMappings(
        AssignUserRolesDto $dto,
        string $method,
    ): void {
        $roles = $dto->getRoles();
        if ($roles === []) {
            return;
        }

        foreach ($roles as $role) {
            Assert::that($role)->isInstanceOf(RoleDto::class);
        }

        $token = $this->getAccessToken();
        $endpoint = $this->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/users/'
                . $dto->getUserId()->toString()
                . '/role-mappings/realm'
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

        $request = $this->createRequest(
            method: $method,
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpClient->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak user role mapping failed with status %d: %s', $statusCode, $body)
        );
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
