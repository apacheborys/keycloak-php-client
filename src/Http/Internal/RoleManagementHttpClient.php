<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\Http\RoleManagementHttpClientInterface;
use Assert\Assert;
use RuntimeException;

final readonly class RoleManagementHttpClient implements RoleManagementHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProvider $accessTokenProvider,
    ) {
    }

    /**
     * @return list<RoleDto>
     */
    #[\Override]
    public function getRoles(GetRolesDto $dto): array
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/roles');

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
                message: sprintf('Keycloak get roles failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, mixed> $data */
        $roles = [];
        foreach ($data as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $roles[] = RoleDto::fromArray(data: $item);
        }

        return $roles;
    }

    /**
     * @return list<RoleDto>
     */
    #[\Override]
    public function getAvailableUserRoles(GetUserAvailableRolesDto $dto): array
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm()
                . '/users/' . $dto->getUserId()->toString()
                . '/role-mappings/realm/available'
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
                message: sprintf('Keycloak get available user roles failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, mixed> $data */
        $roles = [];
        foreach ($data as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $roles[] = RoleDto::fromArray(data: $item);
        }

        return $roles;
    }

    #[\Override]
    public function createRole(CreateRoleDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/roles');

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

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        if ($statusCode === 409) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf('Keycloak create role failed with status %d: %s', $statusCode, $body)
        );
    }

    #[\Override]
    public function deleteRole(DeleteRoleDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/roles/' . rawurlencode($dto->getRoleName())
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
            message: sprintf('Keycloak delete role failed with status %d: %s', $statusCode, $body)
        );
    }

    #[\Override]
    public function assignRolesToUser(AssignUserRolesDto $dto): void
    {
        $this->changeUserRoleMappings(
            dto: $dto,
            method: 'POST',
        );
    }

    #[\Override]
    public function unassignRolesFromUser(AssignUserRolesDto $dto): void
    {
        $this->changeUserRoleMappings(
            dto: $dto,
            method: 'DELETE',
        );
    }

    private function changeUserRoleMappings(AssignUserRolesDto $dto, string $method): void
    {
        $roles = $dto->getRoles();
        if ($roles === []) {
            return;
        }

        foreach ($roles as $role) {
            Assert::that($role)->isInstanceOf(RoleDto::class);
        }

        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/users/'
                . $dto->getUserId()->toString()
                . '/role-mappings/realm'
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->toArray(), flags: JSON_THROW_ON_ERROR);

        $request = $this->httpCore->createRequest(
            method: $method,
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
            message: sprintf('Keycloak user role mapping failed with status %d: %s', $statusCode, $body)
        );
    }
}
