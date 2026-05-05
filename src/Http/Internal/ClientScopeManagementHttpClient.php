<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\Http\ClientScopeManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use Assert\Assert;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

final readonly class ClientScopeManagementHttpClient implements ClientScopeManagementHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProvider $accessTokenProvider,
    ) {
    }

    /**
     * @return list<ClientScopeDto>
     */
    #[\Override]
    public function getClientScopes(GetClientScopesDto $dto): array
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/client-scopes');

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
                message: sprintf('Keycloak get client scopes failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, mixed> $data */
        $clientScopes = [];
        foreach ($data as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $clientScopes[] = ClientScopeDto::fromArray(data: $item);
        }

        return $clientScopes;
    }

    #[\Override]
    public function getClientScopeById(GetClientScopeByIdDto $dto): ClientScopeDto
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/client-scopes/' . $dto->getClientScopeId()->toString()
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
                message: sprintf('Keycloak get client scope by id failed with status %d: %s', $statusCode, $body)
            );
        }

        return ClientScopeDto::fromArray(
            data: $this->httpCore->decodeJson(body: $body),
        );
    }

    /**
     * @return list<ClientScopesProtocolMapperDto>
     */
    #[\Override]
    public function getClientScopeProtocolMappers(GetClientScopeProtocolMappersDto $dto): array
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/client-scopes/'
                . $dto->getClientScopeId()->toString()
                . '/protocol-mappers/models'
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
                message: sprintf(
                    'Keycloak get client scope protocol mappers failed with status %d: %s',
                    $statusCode,
                    $body,
                )
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, mixed> $data */
        $protocolMappers = [];
        foreach ($data as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $protocolMappers[] = ClientScopesProtocolMapperDto::fromArray(data: $item);
        }

        return $protocolMappers;
    }

    #[\Override]
    public function createClientScope(CreateClientScopeDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $dto->getRealm() . '/client-scopes');

        /** @var string $payload */
        $payload = json_encode(value: $dto->getClientScope()->toArray(), flags: JSON_THROW_ON_ERROR);

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
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak create client scope failed with status %d: %s', $statusCode, $body)
            );
        }

        $assignmentType = $dto->getRealmAssignmentType();
        if ($assignmentType === null) {
            return;
        }

        $clientScopeId = $this->resolveCreatedClientScopeId(
            realm: $dto->getRealm(),
            scopeName: $dto->getClientScope()->getName(),
            locationHeader: $response->getHeaderLine('Location'),
        );

        $this->applyInitialRealmAssignment(
            realm: $dto->getRealm(),
            clientScopeId: $clientScopeId,
            assignmentType: $assignmentType,
        );
    }

    #[\Override]
    public function updateClientScope(UpdateClientScopeDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/client-scopes/' . $dto->getClientScopeId()->toString()
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->getClientScope()->toArray(), flags: JSON_THROW_ON_ERROR);

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
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak update client scope failed with status %d: %s', $statusCode, $body)
            );
        }

        $assignmentType = $dto->getRealmAssignmentType();
        if ($assignmentType === null) {
            return;
        }

        $this->synchronizeRealmAssignment(
            realm: $dto->getRealm(),
            clientScopeId: $dto->getClientScopeId(),
            assignmentType: $assignmentType,
        );
    }

    #[\Override]
    public function deleteClientScope(DeleteClientScopeDto $dto): void
    {
        $clientScopeId = $dto->getClientScopeId();
        if ($dto->shouldRemoveFromRealmDefaultAssignmentsBeforeDelete()) {
            $this->removeFromRealmDefaultClientScopes(
                realm: $dto->getRealm(),
                clientScopeId: $clientScopeId,
            );
            $this->removeFromRealmOptionalClientScopes(
                realm: $dto->getRealm(),
                clientScopeId: $clientScopeId,
            );
        }

        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/' . $dto->getRealm() . '/client-scopes/' . $clientScopeId->toString()
        );
        $request = $this->httpCore->createRequest(
            method: 'DELETE',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        throw new RuntimeException(
            message: sprintf('Keycloak delete client scope failed with status %d: %s', $statusCode, $body)
        );
    }

    #[\Override]
    public function createClientScopeProtocolMapper(CreateClientScopeProtocolMapperDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/client-scopes/'
                . $dto->getClientScopeId()->toString()
                . '/protocol-mappers/models'
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->getProtocolMapper()->toArray(), flags: JSON_THROW_ON_ERROR);

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

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf(
                'Keycloak create client scope protocol mapper failed with status %d: %s',
                $statusCode,
                $body
            )
        );
    }

    #[\Override]
    public function updateClientScopeProtocolMapper(UpdateClientScopeProtocolMapperDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/client-scopes/'
                . $dto->getClientScopeId()->toString()
                . '/protocol-mappers/models/'
                . $dto->getProtocolMapperId()->toString()
        );

        /** @var string $payload */
        $payload = json_encode(value: $dto->getProtocolMapper()->toArray(), flags: JSON_THROW_ON_ERROR);

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
            message: sprintf(
                'Keycloak update client scope protocol mapper failed with status %d: %s',
                $statusCode,
                $body
            )
        );
    }

    #[\Override]
    public function deleteClientScopeProtocolMapper(DeleteClientScopeProtocolMapperDto $dto): void
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms/'
                . $dto->getRealm()
                . '/client-scopes/'
                . $dto->getClientScopeId()->toString()
                . '/protocol-mappers/models/'
                . $dto->getProtocolMapperId()->toString()
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
            message: sprintf(
                'Keycloak delete client scope protocol mapper failed with status %d: %s',
                $statusCode,
                $body
            )
        );
    }

    private function synchronizeRealmAssignment(
        string $realm,
        UuidInterface $clientScopeId,
        ClientScopeRealmAssignmentType $assignmentType,
    ): void {
        if ($assignmentType === ClientScopeRealmAssignmentType::NONE) {
            $this->removeFromRealmDefaultClientScopes(realm: $realm, clientScopeId: $clientScopeId);
            $this->removeFromRealmOptionalClientScopes(realm: $realm, clientScopeId: $clientScopeId);

            return;
        }

        if ($assignmentType === ClientScopeRealmAssignmentType::DEFAULT) {
            $this->assignToRealmDefaultClientScopes(realm: $realm, clientScopeId: $clientScopeId);
            $this->removeFromRealmOptionalClientScopes(realm: $realm, clientScopeId: $clientScopeId);

            return;
        }

        $this->assignToRealmOptionalClientScopes(realm: $realm, clientScopeId: $clientScopeId);
        $this->removeFromRealmDefaultClientScopes(realm: $realm, clientScopeId: $clientScopeId);
    }

    private function applyInitialRealmAssignment(
        string $realm,
        UuidInterface $clientScopeId,
        ClientScopeRealmAssignmentType $assignmentType,
    ): void {
        if ($assignmentType === ClientScopeRealmAssignmentType::DEFAULT) {
            $this->assignToRealmDefaultClientScopes(realm: $realm, clientScopeId: $clientScopeId);

            return;
        }

        if ($assignmentType !== ClientScopeRealmAssignmentType::OPTIONAL) {
            return;
        }

        $this->assignToRealmOptionalClientScopes(realm: $realm, clientScopeId: $clientScopeId);
    }

    private function assignToRealmDefaultClientScopes(string $realm, UuidInterface $clientScopeId): void
    {
        $this->changeRealmClientScopeAssignment(
            method: 'PUT',
            realm: $realm,
            path: '/admin/realms/' . $realm . '/default-default-client-scopes/' . $clientScopeId->toString(),
            action: 'assign client scope to realm defaults',
        );
    }

    private function assignToRealmOptionalClientScopes(string $realm, UuidInterface $clientScopeId): void
    {
        $this->changeRealmClientScopeAssignment(
            method: 'PUT',
            realm: $realm,
            path: '/admin/realms/' . $realm . '/default-optional-client-scopes/' . $clientScopeId->toString(),
            action: 'assign client scope to realm optionals',
        );
    }

    private function removeFromRealmDefaultClientScopes(string $realm, UuidInterface $clientScopeId): void
    {
        $this->changeRealmClientScopeAssignment(
            method: 'DELETE',
            realm: $realm,
            path: '/admin/realms/' . $realm . '/default-default-client-scopes/' . $clientScopeId->toString(),
            action: 'remove client scope from realm defaults',
            ignoreNotFound: true,
        );
    }

    private function removeFromRealmOptionalClientScopes(string $realm, UuidInterface $clientScopeId): void
    {
        $this->changeRealmClientScopeAssignment(
            method: 'DELETE',
            realm: $realm,
            path: '/admin/realms/' . $realm . '/default-optional-client-scopes/' . $clientScopeId->toString(),
            action: 'remove client scope from realm optionals',
            ignoreNotFound: true,
        );
    }

    private function changeRealmClientScopeAssignment(
        string $method,
        string $realm,
        string $path,
        string $action,
        bool $ignoreNotFound = false,
    ): void {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: $path);
        $request = $this->httpCore->createRequest(
            method: $method,
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        if ($ignoreNotFound && $statusCode === 404) {
            return;
        }

        if ($method === 'PUT' && $statusCode === 409) {
            return;
        }

        $body = (string) $response->getBody();
        throw new RuntimeException(
            message: sprintf(
                'Keycloak %s failed in realm "%s" with status %d: %s',
                $action,
                $realm,
                $statusCode,
                $body
            )
        );
    }

    private function resolveCreatedClientScopeId(
        string $realm,
        string $scopeName,
        string $locationHeader,
    ): UuidInterface {
        $locationBasedId = $this->extractUuidFromLocation(locationHeader: $locationHeader);
        if ($locationBasedId instanceof UuidInterface) {
            return $locationBasedId;
        }

        $scopes = $this->getClientScopes(dto: new GetClientScopesDto(realm: $realm));
        foreach ($scopes as $scope) {
            if ($scope->getName() !== $scopeName) {
                continue;
            }

            $scopeId = $scope->getId();
            if ($scopeId instanceof UuidInterface) {
                return $scopeId;
            }
        }

        throw new RuntimeException(
            message: sprintf(
                'Unable to resolve created client scope id for scope "%s" in realm "%s".',
                $scopeName,
                $realm,
            )
        );
    }

    private function extractUuidFromLocation(string $locationHeader): ?UuidInterface
    {
        if ($locationHeader === '') {
            return null;
        }

        if (!preg_match('/([0-9a-fA-F\\-]{36})$/', $locationHeader, $matches)) {
            return null;
        }

        $candidate = $matches[1];
        if (!Uuid::isValid($candidate)) {
            return null;
        }

        return Uuid::fromString($candidate);
    }
}
