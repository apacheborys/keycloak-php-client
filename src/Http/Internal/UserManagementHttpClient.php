<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Exception\CreateUserException;
use Apacheborys\KeycloakPhpClient\Http\UserManagementHttpClientInterface;
use Assert\Assert;
use LogicException;
use RuntimeException;
use Ramsey\Uuid\UuidInterface;

final readonly class UserManagementHttpClient implements UserManagementHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProvider $accessTokenProvider,
    ) {
    }

    /**
     * @return list<KeycloakUser>
     */
    #[\Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        $token = $this->accessTokenProvider->getAccessToken();

        $query = $this->buildUsersQuery(dto: $dto);
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/users', query: $query);
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
                message: sprintf('Keycloak users request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, array<string, mixed>> $data */
        $users = [];
        foreach ($data as $userData) {
            Assert::that($userData)->isArray();
            $users[] = KeycloakUser::fromArray(data: $userData);
        }

        return $users;
    }

    #[\Override]
    public function getUserById(GetUserByIdDto $dto): KeycloakUser
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/users/' . $dto->getUserId()->toString()
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
                message: sprintf('Keycloak get user by id failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);
        Assert::that($data)->isArray();

        /** @var array<string, mixed> $data */
        return KeycloakUser::fromArray(data: $data);
    }

    #[\Override]
    public function createUser(CreateUserDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $dto->getProfile()->getRealm() . '/users');

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

        $request = $this->httpCore->createRequest(
            method: 'POST',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 201) {
            return;
        }

        throw new CreateUserException(message: (string) $response->getBody());
    }

    #[\Override]
    public function updateUser(UpdateUserDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $userId = $this->requireUserId(userId: $dto->getUserId(), operation: 'update user');
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/users/' . $userId->toString()
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

        $request = $this->httpCore->createRequest(
            method: 'PUT',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak update user failed with status %d: %s', $statusCode, $body)
        );
    }

    #[\Override]
    public function deleteUser(DeleteUserDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $userId = $this->requireUserId(userId: $dto->getUserId(), operation: 'delete user');
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/users/' . $userId->toString()
        );

        $request = $this->httpCore->createRequest(
            method: 'DELETE',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak delete user failed with status %d: %s', $statusCode, $body)
        );
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    #[\Override]
    public function createRealm(array $payload): array
    {
        throw new LogicException(message: 'HTTP createRealm is not implemented yet.');
    }

    #[\Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/users/'
                . $dto->getUser()->getKeycloakId()
                . '/reset-password'
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

        $request = $this->httpCore->createRequest(
            method: 'PUT',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 204) {
            return;
        }

        throw new LogicException("Can't set password, response: " . $response->getBody()->getContents());
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

    private function requireUserId(?UuidInterface $userId, string $operation): UuidInterface
    {
        if ($userId instanceof UuidInterface) {
            return $userId;
        }

        throw new LogicException(sprintf('Keycloak user id is required to %s through HTTP client.', $operation));
    }
}
