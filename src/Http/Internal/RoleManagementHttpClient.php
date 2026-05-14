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
use Apacheborys\KeycloakPhpClient\Token\AccessTokenProviderInterface;
use Assert\Assert;

final readonly class RoleManagementHttpClient implements RoleManagementHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProviderInterface $accessTokenProvider,
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
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static function (array $data): array {
                Assert::that(array_is_list($data))->true();

                $roles = [];
                foreach ($data as $item) {
                    Assert::that($item)->isArray();
                    /** @var array<string, mixed> $item */
                    $roles[] = RoleDto::fromArray(data: $item);
                }

                return $roles;
            },
        );
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
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static function (array $data): array {
                Assert::that(array_is_list($data))->true();

                $roles = [];
                foreach ($data as $item) {
                    Assert::that($item)->isArray();
                    /** @var array<string, mixed> $item */
                    $roles[] = RoleDto::fromArray(data: $item);
                }

                return $roles;
            },
        );
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
        $this->httpCore->assertSuccessfulResponseOrAllowedStatus(
            request: $request,
            response: $response,
            allowedStatusCodes: [409],
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
        $this->httpCore->assertSuccessfulResponse(
            request: $request,
            response: $response,
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
        $this->httpCore->assertSuccessfulResponse(
            request: $request,
            response: $response,
        );
    }
}
