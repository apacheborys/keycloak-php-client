<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\UserManagementHttpClientInterface;
use Assert\Assert;
use LogicException;
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
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static function (array $data): array {
                Assert::that(array_is_list($data))->true();

                $users = [];
                foreach ($data as $userData) {
                    Assert::that($userData)->isArray();
                    /** @var array<string, mixed> $userData */
                    $users[] = KeycloakUser::fromArray(data: $userData);
                }

                return $users;
            },
        );
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
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static fn (array $data): KeycloakUser => KeycloakUser::fromArray(data: $data),
        );
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
        $this->httpCore->assertSuccessfulResponse(
            request: $request,
            response: $response,
        );
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
        $this->httpCore->assertSuccessfulResponse(
            request: $request,
            response: $response,
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
        $this->httpCore->assertSuccessfulResponse(
            request: $request,
            response: $response,
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
        $this->httpCore->assertSuccessfulResponse(
            request: $request,
            response: $response,
        );
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
