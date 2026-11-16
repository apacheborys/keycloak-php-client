<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;

/**
 * Client scope and protocol mapper CRUD through the HTTP facade.
 */
final class ClientScopeManagementE2ETest extends KeycloakE2ETestCase
{
    public function testClientScopeAndProtocolMapperLifecycle(): void
    {
        $scopeName = $this->uniqueName('e2e-scope');
        $this->trackClientScope($scopeName);

        // create scope (optional for all clients in the realm)
        $this->httpClient->createClientScope(new CreateClientScopeDto(
            realm: $this->realm,
            clientScope: new ClientScopeDto(
                name: $scopeName,
                protocol: 'openid-connect',
                description: 'Created by the E2E suite',
                attributes: ['include.in.token.scope' => 'true', 'display.on.consent.screen' => 'false'],
            ),
            realmAssignmentType: ClientScopeRealmAssignmentType::OPTIONAL,
        ));

        $scope = $this->findClientScopeByName($scopeName);
        self::assertNotNull($scope, 'Created scope must be listed by getClientScopes().');
        $scopeId = $scope->getId();
        self::assertNotNull($scopeId);

        $fetched = $this->httpClient->getClientScopeById(new GetClientScopeByIdDto(realm: $this->realm, clientScopeId: $scopeId));
        self::assertSame($scopeName, $fetched->getName());
        self::assertSame('Created by the E2E suite', $fetched->getDescription());
        self::assertSame('true', $fetched->getAttributes()['include.in.token.scope'] ?? null);
        self::assertSame([], $fetched->getProtocolMappers());

        // add a user-attribute mapper
        $mapperName = $this->uniqueName('e2e-mapper');
        $this->httpClient->createClientScopeProtocolMapper(new CreateClientScopeProtocolMapperDto(
            realm: $this->realm,
            clientScopeId: $scopeId,
            protocolMapper: new ClientScopesProtocolMapperDto(
                name: $mapperName,
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
                config: [
                    'user.attribute' => 'e2e-attribute',
                    'claim.name' => 'e2e_claim',
                    'jsonType.label' => 'String',
                    'id.token.claim' => 'true',
                    'access.token.claim' => 'true',
                    'userinfo.token.claim' => 'true',
                ],
            ),
        ));

        $mapper = $this->findProtocolMapperByName($fetched, $mapperName);
        self::assertNotNull($mapper, 'Created mapper must be listed by getClientScopeProtocolMappers().');
        $mapperId = $mapper->getId();
        self::assertNotNull($mapperId);
        self::assertSame('e2e_claim', $mapper->getConfig()->get('claim.name'));
        self::assertSame('e2e-attribute', $mapper->getConfig()->get('user.attribute'));

        // update the mapper's claim name, keeping the rest of its config
        $this->httpClient->updateClientScopeProtocolMapper(new UpdateClientScopeProtocolMapperDto(
            realm: $this->realm,
            clientScopeId: $scopeId,
            protocolMapperId: $mapperId,
            protocolMapper: new ClientScopesProtocolMapperDto(
                id: $mapperId,
                name: $mapperName,
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
                config: ['claim.name' => 'e2e_claim_renamed'] + $mapper->getConfigAsArray(),
            ),
        ));
        $updatedMapper = $this->findProtocolMapperByName($fetched, $mapperName);
        self::assertSame('e2e_claim_renamed', $updatedMapper?->getConfig()->get('claim.name'));

        // update the scope itself and move it to the realm default scopes
        $this->httpClient->updateClientScope(new UpdateClientScopeDto(
            realm: $this->realm,
            clientScopeId: $scopeId,
            clientScope: new ClientScopeDto(
                id: $scopeId,
                name: $scopeName,
                protocol: 'openid-connect',
                description: 'Updated by the E2E suite',
                attributes: $fetched->getAttributes(),
            ),
            realmAssignmentType: ClientScopeRealmAssignmentType::DEFAULT,
        ));
        $updatedScope = $this->httpClient->getClientScopeById(new GetClientScopeByIdDto(realm: $this->realm, clientScopeId: $scopeId));
        self::assertSame('Updated by the E2E suite', $updatedScope->getDescription());
        self::assertCount(1, $updatedScope->getProtocolMappers(), 'Updating the scope must not drop its mappers.');

        // delete mapper, then scope
        $this->httpClient->deleteClientScopeProtocolMapper(new DeleteClientScopeProtocolMapperDto(
            realm: $this->realm,
            clientScopeId: $scopeId,
            protocolMapperId: $mapperId,
        ));
        self::assertNull($this->findProtocolMapperByName($fetched, $mapperName));

        $this->httpClient->deleteClientScope(new DeleteClientScopeDto(realm: $this->realm, clientScopeId: $scopeId));

        $this->expectException(KeycloakNotFoundException::class);
        $this->httpClient->getClientScopeById(new GetClientScopeByIdDto(realm: $this->realm, clientScopeId: $scopeId));
    }
}
