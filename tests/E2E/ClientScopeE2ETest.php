<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

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
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2ETestCase;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\UuidInterface;

/**
 * Full create/read/update/delete round trip for a client scope and one of its protocol mappers,
 * using a per-run scope so the realm's built-in scopes are never touched.
 */
#[Group('e2e')]
final class ClientScopeE2ETest extends E2ETestCase
{
    private static KeycloakHttpClientInterface $httpClient;

    private ?UuidInterface $scopeId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$httpClient = self::createHttpClient();
    }

    protected function tearDown(): void
    {
        $scopeId = $this->scopeId;
        if ($scopeId === null) {
            return;
        }

        self::cleanup(static function () use ($scopeId): void {
            try {
                self::$httpClient->deleteClientScope(
                    new DeleteClientScopeDto(realm: self::$config->realm, clientScopeId: $scopeId),
                );
            } catch (KeycloakNotFoundException) {
                // deleted by the test itself
            }
        });
    }

    public function testClientScopeAndProtocolMapperRoundTrip(): void
    {
        $realm = self::$config->realm;
        $scopeName = self::resourceName('scope');

        self::$httpClient->createClientScope(
            new CreateClientScopeDto(
                realm: $realm,
                clientScope: self::scope(name: $scopeName, description: 'created by e2e'),
                realmAssignmentType: ClientScopeRealmAssignmentType::OPTIONAL,
            ),
        );
        $scopeId = $this->scopeId = self::findScopeIdByName($scopeName);

        self::$httpClient->updateClientScope(
            new UpdateClientScopeDto(
                realm: $realm,
                clientScopeId: $scopeId,
                clientScope: self::scope(name: $scopeName, description: 'updated by e2e', id: $scopeId),
                realmAssignmentType: ClientScopeRealmAssignmentType::NONE,
            ),
        );
        $reloaded = self::$httpClient->getClientScopeById(
            new GetClientScopeByIdDto(realm: $realm, clientScopeId: $scopeId),
        );
        self::assertSame('updated by e2e', $reloaded->getDescription());

        self::$httpClient->createClientScopeProtocolMapper(
            new CreateClientScopeProtocolMapperDto(
                realm: $realm,
                clientScopeId: $scopeId,
                protocolMapper: self::hardcodedClaimMapper(claimValue: 'first'),
            ),
        );
        $mapper = self::findSingleMapper($scopeId);
        $mapperId = $mapper->getId();
        self::assertInstanceOf(UuidInterface::class, $mapperId);
        self::assertSame('first', $mapper->getConfigAsArray()['claim.value'] ?? null);

        self::$httpClient->updateClientScopeProtocolMapper(
            new UpdateClientScopeProtocolMapperDto(
                realm: $realm,
                clientScopeId: $scopeId,
                protocolMapperId: $mapperId,
                protocolMapper: self::hardcodedClaimMapper(claimValue: 'second', id: $mapperId),
            ),
        );
        self::assertSame('second', self::findSingleMapper($scopeId)->getConfigAsArray()['claim.value'] ?? null);

        self::$httpClient->deleteClientScopeProtocolMapper(
            new DeleteClientScopeProtocolMapperDto(realm: $realm, clientScopeId: $scopeId, protocolMapperId: $mapperId),
        );
        self::assertSame(
            [],
            self::$httpClient->getClientScopeProtocolMappers(
                new GetClientScopeProtocolMappersDto(realm: $realm, clientScopeId: $scopeId),
            ),
        );

        self::$httpClient->deleteClientScope(new DeleteClientScopeDto(realm: $realm, clientScopeId: $scopeId));

        $this->expectException(KeycloakNotFoundException::class);
        self::$httpClient->getClientScopeById(new GetClientScopeByIdDto(realm: $realm, clientScopeId: $scopeId));
    }

    private static function scope(string $name, string $description, ?UuidInterface $id = null): ClientScopeDto
    {
        return new ClientScopeDto(
            name: $name,
            protocol: 'openid-connect',
            description: $description,
            attributes: [
                'include.in.token.scope' => 'true',
                'display.on.consent.screen' => 'false',
            ],
            id: $id,
        );
    }

    private static function hardcodedClaimMapper(
        string $claimValue,
        ?UuidInterface $id = null,
    ): ClientScopesProtocolMapperDto {
        return new ClientScopesProtocolMapperDto(
            name: self::resourceName('claim'),
            protocol: 'openid-connect',
            protocolMapper: 'oidc-hardcoded-claim-mapper',
            config: [
                'claim.name' => 'e2e_claim',
                'claim.value' => $claimValue,
                'jsonType.label' => 'String',
                'access.token.claim' => 'true',
                'id.token.claim' => 'false',
                'userinfo.token.claim' => 'false',
            ],
            id: $id,
        );
    }

    private static function findScopeIdByName(string $name): UuidInterface
    {
        foreach (self::$httpClient->getClientScopes(new GetClientScopesDto(realm: self::$config->realm)) as $scope) {
            if ($scope->getName() === $name && $scope->getId() !== null) {
                return $scope->getId();
            }
        }

        self::fail(sprintf('Client scope "%s" was not found after creation.', $name));
    }

    private static function findSingleMapper(UuidInterface $scopeId): ClientScopesProtocolMapperDto
    {
        $mappers = self::$httpClient->getClientScopeProtocolMappers(
            new GetClientScopeProtocolMappersDto(realm: self::$config->realm, clientScopeId: $scopeId),
        );
        self::assertCount(1, $mappers);

        return $mappers[0];
    }
}
