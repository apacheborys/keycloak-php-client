<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\Http\Internal\ClientScopeManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\AccessTokenProvider;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Http\Internal\OidcInteractionHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\RealmSettingsManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\RoleManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Http\Internal\UserManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Tests\Support\Cache\InMemoryCachePool;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18Client;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\MockServer\PhpMockServer;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class InternalHttpClientIntegrationTest extends TestCase
{
    private ?PhpMockServer $server = null;
    private InMemoryCachePool $cache;
    private KeycloakHttpCore $httpCore;
    private AccessTokenProvider $accessTokenProvider;

    protected function setUp(): void
    {
        try {
            $this->server = new PhpMockServer();
        } catch (RuntimeException $exception) {
            $this->markTestSkipped(
                'Local HTTP server is not available in this environment: ' . $exception->getMessage()
            );
        }

        $this->cache = new InMemoryCachePool();
        $this->httpCore = new KeycloakHttpCore(
            baseUrl: $this->server->getBaseUrl(),
            httpClient: new NativePsr18Client(),
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
        );
        $this->accessTokenProvider = new AccessTokenProvider(
            httpCore: $this->httpCore,
            clientRealm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            cache: $this->cache,
        );
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    public function testUserManagementGetUsersSendsExpectedRequestAndParsesResponse(): void
    {
        $this->seedAccessToken();
        $this->server->setScenario(
            [
                'GET /admin/realms/master/users?email=user%40example.com&first=0&max=20&exact=false' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                [
                                    'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                                    'username' => 'user@example.com',
                                    'createdTimestamp' => 1_700_000_000_000,
                                    'attributes' => [
                                        'external-user-id' => ['external-id-789'],
                                        'locale' => ['en'],
                                    ],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $client = new UserManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );
        $users = $client->getUsers(new SearchUsersDto(realm: 'master', email: 'user@example.com'));

        self::assertCount(1, $users);
        self::assertSame('user@example.com', $users[0]->getUsername());
        self::assertSame(
            [
                'external-user-id' => ['external-id-789'],
                'locale' => ['en'],
            ],
            $users[0]->getAttributes(),
        );

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame(
            '/admin/realms/master/users?email=user%40example.com&first=0&max=20&exact=false',
            $requests[0]['uri']
        );
        self::assertSame('Keycloak PHP Client', $requests[0]['headers']['user-agent'] ?? '');
        self::assertStringStartsWith('Bearer ', $requests[0]['headers']['authorization'] ?? '');
    }

    public function testRoleManagementCreateRoleSendsExpectedRequestBody(): void
    {
        $this->seedAccessToken();
        $this->server->setScenario(
            [
                'POST /admin/realms/master/roles' => [
                    [
                        'status' => 201,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
            ],
        );

        $client = new RoleManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );
        $client->createRole(
            new CreateRoleDto(
                realm: 'master',
                role: new \Apacheborys\KeycloakPhpClient\DTO\RoleDto(
                    name: 'my-role',
                    description: 'Role for test',
                ),
            ),
        );

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('/admin/realms/master/roles', $requests[0]['uri']);
        self::assertSame('application/json', $requests[0]['headers']['content-type'] ?? '');

        $payload = json_decode($requests[0]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('my-role', $payload['name'] ?? null);
        self::assertSame('Role for test', $payload['description'] ?? null);
        self::assertFalse((bool) ($payload['composite'] ?? true));
        self::assertFalse((bool) ($payload['clientRole'] ?? true));
    }

    public function testUserManagementCreateUserSendsAttributesInPayload(): void
    {
        $this->seedAccessToken();
        $this->server->setScenario(
            [
                'POST /admin/realms/master/users' => [
                    [
                        'status' => 201,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
            ],
        );

        $client = new UserManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $client->createUser(
            new CreateUserDto(
                profile: new CreateUserProfileDto(
                    username: 'test-user',
                    email: 'test@example.com',
                    emailVerified: false,
                    enabled: true,
                    firstName: 'Test',
                    lastName: 'User',
                    realm: 'master',
                    attributes: [
                        'locale' => '',
                        'external-user-id' => 'external-id-123',
                    ],
                ),
            ),
        );

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('/admin/realms/master/users', $requests[0]['uri']);

        $payload = json_decode($requests[0]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            [
                'locale' => [''],
                'external-user-id' => ['external-id-123'],
            ],
            $payload['attributes'] ?? null,
        );
    }

    public function testUserManagementUpdateUserSendsAttributesInPayload(): void
    {
        $this->seedAccessToken();
        $this->server->setScenario(
            [
                'PUT /admin/realms/master/users/92a372d5-c338-4e77-a1b3-08771241036e' => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
            ],
        );

        $client = new UserManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $client->updateUser(
            new UpdateUserDto(
                realm: 'master',
                userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
                profile: new UpdateUserProfileDto(
                    username: 'test-user',
                    attributes: [
                        'external-user-id' => 'external-id-456',
                    ],
                ),
            ),
        );

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('PUT', $requests[0]['method']);
        self::assertSame('/admin/realms/master/users/92a372d5-c338-4e77-a1b3-08771241036e', $requests[0]['uri']);

        $payload = json_decode($requests[0]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            [
                'external-user-id' => ['external-id-456'],
            ],
            $payload['attributes'] ?? null,
        );
    }

    public function testClientScopeManagementGetClientScopesParsesResponse(): void
    {
        $this->seedAccessToken();
        $this->server->setScenario(
            [
                'GET /admin/realms/master/client-scopes' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                [
                                    'id' => '39c0fcbc-db18-4236-8cae-2c074d730f4b',
                                    'name' => 'backend-dedicated',
                                    'description' => 'Backend client scope',
                                    'protocol' => 'openid-connect',
                                    'attributes' => [
                                        'include.in.token.scope' => 'true',
                                        'display.on.consent.screen' => 'true',
                                    ],
                                    'protocolMappers' => [],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $client = new ClientScopeManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $scopes = $client->getClientScopes(new GetClientScopesDto(realm: 'master'));

        self::assertCount(1, $scopes);
        self::assertSame('backend-dedicated', $scopes[0]->getName());
        self::assertSame(
            '39c0fcbc-db18-4236-8cae-2c074d730f4b',
            $scopes[0]->getId()?->toString(),
        );

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('/admin/realms/master/client-scopes', $requests[0]['uri']);
    }

    public function testClientScopeManagementGetClientScopeByIdParsesResponse(): void
    {
        $this->seedAccessToken();
        $clientScopeId = '39c0fcbc-db18-4236-8cae-2c074d730f4b';

        $this->server->setScenario(
            [
                'GET /admin/realms/master/client-scopes/' . $clientScopeId => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'id' => $clientScopeId,
                                'name' => 'backend-dedicated',
                                'description' => 'Backend client scope',
                                'protocol' => 'openid-connect',
                                'attributes' => [
                                    'include.in.token.scope' => 'true',
                                    'display.on.consent.screen' => 'true',
                                    'gui.order' => '',
                                    'consent.screen.text' => '',
                                ],
                                'protocolMappers' => [
                                    [
                                        'id' => 'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
                                        'name' => 'External user id attribute',
                                        'protocol' => 'openid-connect',
                                        'protocolMapper' => 'oidc-usermodel-attribute-mapper',
                                        'consentRequired' => false,
                                        'config' => [
                                            'user.attribute' => 'external-user-id',
                                            'claim.name' => 'external_user_id',
                                            'jsonType.label' => 'String',
                                        ],
                                    ],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $client = new ClientScopeManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $scope = $client->getClientScopeById(
            new GetClientScopeByIdDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
            ),
        );

        self::assertSame('backend-dedicated', $scope->getName());
        self::assertSame($clientScopeId, $scope->getId()?->toString());
        self::assertCount(1, $scope->getProtocolMappers());
        self::assertSame('external_user_id', $scope->getProtocolMappers()[0]->getConfig()->get('claim.name'));

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('/admin/realms/master/client-scopes/' . $clientScopeId, $requests[0]['uri']);
    }

    public function testClientScopeManagementGetClientScopeProtocolMappersParsesResponse(): void
    {
        $this->seedAccessToken();
        $clientScopeId = '39c0fcbc-db18-4236-8cae-2c074d730f4b';

        $this->server->setScenario(
            [
                'GET /admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                [
                                    'id' => 'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
                                    'name' => 'External user id attribute',
                                    'protocol' => 'openid-connect',
                                    'protocolMapper' => 'oidc-usermodel-attribute-mapper',
                                    'consentRequired' => false,
                                    'config' => [
                                        'user.attribute' => 'external-user-id',
                                        'claim.name' => 'external_user_id',
                                        'jsonType.label' => 'String',
                                    ],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $client = new ClientScopeManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $protocolMappers = $client->getClientScopeProtocolMappers(
            new GetClientScopeProtocolMappersDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
            ),
        );

        self::assertCount(1, $protocolMappers);
        self::assertSame(
            'external_user_id',
            $protocolMappers[0]->getConfig()->get('claim.name'),
        );

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame(
            '/admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models',
            $requests[0]['uri'],
        );
    }

    public function testClientScopeManagementCreateUpdateDeleteWithRealmAssignment(): void
    {
        $this->seedAccessToken();
        $clientScopeId = 'f480fece-9dc0-41e6-9a6a-ac25137d800e';

        $this->server->setScenario(
            [
                'POST /admin/realms/master/client-scopes' => [
                    [
                        'status' => 201,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Location' => $this->server->getBaseUrl() . '/admin/realms/master/client-scopes/' . $clientScopeId,
                        ],
                        'body' => '',
                    ],
                ],
                'PUT /admin/realms/master/default-default-client-scopes/' . $clientScopeId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'PUT /admin/realms/master/client-scopes/' . $clientScopeId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'PUT /admin/realms/master/default-optional-client-scopes/' . $clientScopeId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'DELETE /admin/realms/master/default-default-client-scopes/' . $clientScopeId => [
                    [
                        'status' => 404,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                    [
                        'status' => 404,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'DELETE /admin/realms/master/default-optional-client-scopes/' . $clientScopeId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'DELETE /admin/realms/master/client-scopes/' . $clientScopeId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
            ],
        );

        $client = new ClientScopeManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $client->createClientScope(
            new CreateClientScopeDto(
                realm: 'master',
                clientScope: new ClientScopeDto(
                    name: 'test-client-scope',
                    protocol: 'openid-connect',
                    description: 'This is a test',
                    attributes: [
                        'display.on.consent.screen' => 'true',
                        'consent.screen.text' => '',
                        'include.in.token.scope' => 'true',
                    ],
                ),
                realmAssignmentType: ClientScopeRealmAssignmentType::DEFAULT,
            ),
        );

        $client->updateClientScope(
            new UpdateClientScopeDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
                clientScope: new ClientScopeDto(
                    id: Uuid::fromString($clientScopeId),
                    name: 'test-client-scope-updated',
                    protocol: 'openid-connect',
                    description: 'This is a test',
                    attributes: [
                        'display.on.consent.screen' => 'true',
                        'consent.screen.text' => '',
                        'include.in.token.scope' => 'true',
                    ],
                ),
                realmAssignmentType: ClientScopeRealmAssignmentType::OPTIONAL,
            ),
        );

        $client->deleteClientScope(
            new DeleteClientScopeDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
            ),
        );

        $requests = $this->server->getRequests();
        self::assertCount(8, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('/admin/realms/master/client-scopes', $requests[0]['uri']);
        self::assertSame('PUT', $requests[1]['method']);
        self::assertSame('/admin/realms/master/default-default-client-scopes/' . $clientScopeId, $requests[1]['uri']);
        self::assertSame('PUT', $requests[2]['method']);
        self::assertSame('/admin/realms/master/client-scopes/' . $clientScopeId, $requests[2]['uri']);
        self::assertSame('PUT', $requests[3]['method']);
        self::assertSame('/admin/realms/master/default-optional-client-scopes/' . $clientScopeId, $requests[3]['uri']);
        self::assertSame('DELETE', $requests[4]['method']);
        self::assertSame('/admin/realms/master/default-default-client-scopes/' . $clientScopeId, $requests[4]['uri']);
        self::assertSame('DELETE', $requests[5]['method']);
        self::assertSame('/admin/realms/master/default-default-client-scopes/' . $clientScopeId, $requests[5]['uri']);
        self::assertSame('DELETE', $requests[6]['method']);
        self::assertSame('/admin/realms/master/default-optional-client-scopes/' . $clientScopeId, $requests[6]['uri']);
        self::assertSame('DELETE', $requests[7]['method']);
        self::assertSame('/admin/realms/master/client-scopes/' . $clientScopeId, $requests[7]['uri']);
    }

    public function testClientScopeManagementCreateUpdateDeleteProtocolMapper(): void
    {
        $this->seedAccessToken();
        $clientScopeId = '39c0fcbc-db18-4236-8cae-2c074d730f4b';
        $mapperId = '3b1caa7b-dad7-4f43-9127-15969f303fe8';

        $this->server->setScenario(
            [
                'POST /admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models' => [
                    [
                        'status' => 201,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'PUT /admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models/' . $mapperId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
                'DELETE /admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models/' . $mapperId => [
                    [
                        'status' => 204,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => '',
                    ],
                ],
            ],
        );

        $client = new ClientScopeManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $client->createClientScopeProtocolMapper(
            new CreateClientScopeProtocolMapperDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
                protocolMapper: new ClientScopesProtocolMapperDto(
                    name: 'External user id attribute',
                    protocol: 'openid-connect',
                    protocolMapper: 'oidc-usermodel-attribute-mapper',
                    config: [
                        'claim.name' => 'external_user_id',
                        'jsonType.label' => 'String',
                        'id.token.claim' => 'true',
                        'access.token.claim' => 'true',
                        'userinfo.token.claim' => 'true',
                        'introspection.token.claim' => 'true',
                        'user.attribute' => 'external-user-id',
                    ],
                ),
            ),
        );

        $client->updateClientScopeProtocolMapper(
            new UpdateClientScopeProtocolMapperDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
                protocolMapperId: Uuid::fromString($mapperId),
                protocolMapper: new ClientScopesProtocolMapperDto(
                    id: Uuid::fromString($mapperId),
                    name: 'External user id attribute',
                    protocol: 'openid-connect',
                    protocolMapper: 'oidc-usermodel-attribute-mapper',
                    config: [
                        'claim.name' => 'external_user_id_test',
                        'jsonType.label' => 'String',
                        'id.token.claim' => 'true',
                        'access.token.claim' => 'true',
                        'userinfo.token.claim' => 'true',
                        'introspection.token.claim' => 'true',
                        'user.attribute' => 'external-user-id',
                    ],
                ),
            ),
        );

        $client->deleteClientScopeProtocolMapper(
            new DeleteClientScopeProtocolMapperDto(
                realm: 'master',
                clientScopeId: Uuid::fromString($clientScopeId),
                protocolMapperId: Uuid::fromString($mapperId),
            ),
        );

        $requests = $this->server->getRequests();
        self::assertCount(3, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('/admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models', $requests[0]['uri']);
        self::assertSame('PUT', $requests[1]['method']);
        self::assertSame('/admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models/' . $mapperId, $requests[1]['uri']);
        self::assertSame('DELETE', $requests[2]['method']);
        self::assertSame('/admin/realms/master/client-scopes/' . $clientScopeId . '/protocol-mappers/models/' . $mapperId, $requests[2]['uri']);

        $createPayload = json_decode($requests[0]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('oidc-usermodel-attribute-mapper', $createPayload['protocolMapper'] ?? null);
        self::assertSame('external-user-id', $createPayload['config']['user.attribute'] ?? null);

        $updatePayload = json_decode($requests[1]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($mapperId, $updatePayload['id'] ?? null);
        self::assertSame('external_user_id_test', $updatePayload['config']['claim.name'] ?? null);
    }

    public function testOidcRequestTokenByPasswordSendsExpectedFormAndParsesResponse(): void
    {
        $token = JwtTestFactory::buildJwtToken();
        $this->server->setScenario(
            [
                'POST /realms/master/protocol/openid-connect/token' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'access_token' => $token,
                                'expires_in' => 3600,
                                'refresh_expires_in' => 1800,
                                'refresh_token' => 'refresh-token',
                                'token_type' => 'Bearer',
                                'not-before-policy' => 0,
                                'scope' => 'email profile',
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $client = new OidcInteractionHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $response = $client->requestTokenByPassword(
            new OidcTokenRequestDto(
                realm: 'master',
                clientId: 'backend',
                clientSecret: 'secret',
                username: 'oleg@example.com',
                password: 'Roadsurfer!2026',
                grantType: OidcGrantType::PASSWORD,
            ),
        );

        self::assertSame('Bearer', $response->getTokenType());
        self::assertSame('refresh-token', $response->getRefreshToken());
        self::assertSame($token, $response->getAccessToken()->getRawToken());

        $requests = $this->server->getRequests();
        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('/realms/master/protocol/openid-connect/token', $requests[0]['uri']);
        self::assertSame(
            'application/x-www-form-urlencoded',
            $requests[0]['headers']['content-type'] ?? ''
        );

        parse_str($requests[0]['body'], $formData);
        self::assertSame('password', $formData['grant_type'] ?? null);
        self::assertSame('backend', $formData['client_id'] ?? null);
        self::assertSame('secret', $formData['client_secret'] ?? null);
        self::assertSame('oleg@example.com', $formData['username'] ?? null);
        self::assertSame('Roadsurfer!2026', $formData['password'] ?? null);
    }

    public function testRealmSettingsManagementSupportsGetCreateUpdateDeleteAttribute(): void
    {
        $this->seedAccessToken();
        $initialProfile = [
            'unmanagedAttributePolicy' => 'ENABLED',
            'attributes' => [
                [
                    'group' => 'user-metadata',
                    'required' => ['roles' => ['admin']],
                    'name' => 'username',
                    'displayName' => '${username}',
                    'validations' => [],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                    'multivalued' => false,
                    'annotations' => [],
                ],
            ],
            'groups' => [
                [
                    'customGroupProperty' => ['enabled' => true],
                    'name' => 'user-metadata',
                    'displayHeader' => 'User metadata',
                    'displayDescription' => 'Attributes, which refer to user metadata',
                    'annotations' => [
                        'collapsed' => false,
                    ],
                ],
            ],
        ];

        $afterCreate = [
            'attributes' => [
                $initialProfile['attributes'][0],
                [
                    'required' => ['roles' => ['admin']],
                    'selector' => ['scopes' => ['openid']],
                    'name' => 'test_attribute',
                    'displayName' => 'Attribute for test reasons',
                    'validations' => [],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                    'multivalued' => false,
                    'annotations' => [],
                ],
            ],
            'groups' => $initialProfile['groups'],
            'unmanagedAttributePolicy' => $initialProfile['unmanagedAttributePolicy'],
        ];

        $afterUpdate = [
            'attributes' => [
                $initialProfile['attributes'][0],
                [
                    'required' => ['roles' => ['admin']],
                    'selector' => ['scopes' => ['openid']],
                    'name' => 'test_attribute',
                    'displayName' => 'Updated attribute',
                    'validations' => [],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                    'multivalued' => false,
                    'annotations' => [],
                ],
            ],
            'groups' => $initialProfile['groups'],
            'unmanagedAttributePolicy' => $initialProfile['unmanagedAttributePolicy'],
        ];

        $afterDelete = $initialProfile;

        $this->server->setScenario(
            [
                'GET /admin/realms/master/users/profile' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($initialProfile, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($initialProfile, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($afterCreate, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($afterUpdate, JSON_THROW_ON_ERROR),
                    ],
                ],
                'PUT /admin/realms/master/users/profile' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($afterCreate, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($afterUpdate, JSON_THROW_ON_ERROR),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($afterDelete, JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        );

        $client = new RealmSettingsManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $fetched = $client->getUserProfile(new GetUserProfileDto(realm: 'master'));
        self::assertCount(1, $fetched->getAttributes());

        $created = $client->createUserProfileAttribute(
            new CreateUserProfileAttributeDto(
                realm: 'master',
                attribute: new AttributeDto(
                    name: 'test_attribute',
                    displayName: 'Attribute for test reasons',
                    permissions: ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                ),
            ),
        );
        self::assertTrue($created->hasAttribute('test_attribute'));

        $updated = $client->updateUserProfileAttribute(
            new UpdateUserProfileAttributeDto(
                realm: 'master',
                attribute: new AttributeDto(
                    name: 'test_attribute',
                    displayName: 'Updated attribute',
                    permissions: ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                ),
            ),
        );
        self::assertTrue($updated->hasAttribute('test_attribute'));

        $deleted = $client->deleteUserProfileAttribute(
            new DeleteUserProfileAttributeDto(
                realm: 'master',
                attributeName: 'test_attribute',
            ),
        );
        self::assertFalse($deleted->hasAttribute('test_attribute'));

        $requests = $this->server->getRequests();
        self::assertCount(7, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('GET', $requests[1]['method']);
        self::assertSame('PUT', $requests[2]['method']);
        self::assertSame('GET', $requests[3]['method']);
        self::assertSame('PUT', $requests[4]['method']);
        self::assertSame('GET', $requests[5]['method']);
        self::assertSame('PUT', $requests[6]['method']);
        self::assertSame('/admin/realms/master/users/profile', $requests[6]['uri']);

        $createPayload = json_decode($requests[2]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ENABLED', $createPayload['unmanagedAttributePolicy'] ?? null);
        self::assertSame('user-metadata', $createPayload['attributes'][0]['group'] ?? null);
        self::assertSame(['roles' => ['admin']], $createPayload['attributes'][0]['required'] ?? null);
        self::assertSame(['enabled' => true], $createPayload['groups'][0]['customGroupProperty'] ?? null);
        self::assertSame(['collapsed' => false], $createPayload['groups'][0]['annotations'] ?? null);

        $updatePayload = json_decode($requests[4]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ENABLED', $updatePayload['unmanagedAttributePolicy'] ?? null);
        self::assertSame(['roles' => ['admin']], $updatePayload['attributes'][1]['required'] ?? null);
        self::assertSame(['scopes' => ['openid']], $updatePayload['attributes'][1]['selector'] ?? null);
        self::assertSame('Updated attribute', $updatePayload['attributes'][1]['displayName'] ?? null);

        $deletePayload = json_decode($requests[6]['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ENABLED', $deletePayload['unmanagedAttributePolicy'] ?? null);
        self::assertSame(['roles' => ['admin']], $deletePayload['attributes'][0]['required'] ?? null);
        self::assertSame(['collapsed' => false], $deletePayload['groups'][0]['annotations'] ?? null);
    }

    public function testRoleManagementGetRolesThrowsForNonSuccessStatus(): void
    {
        $this->seedAccessToken();
        $this->server->setScenario(
            [
                'GET /admin/realms/master/roles' => [
                    [
                        'status' => 500,
                        'headers' => ['Content-Type' => 'text/plain'],
                        'body' => 'internal error',
                    ],
                ],
            ],
        );

        $client = new RoleManagementHttpClient(
            httpCore: $this->httpCore,
            accessTokenProvider: $this->accessTokenProvider,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('status 500');
        $client->getRoles(new GetRolesDto(realm: 'master'));
    }

    private function seedAccessToken(): void
    {
        $cacheKey = 'keycloak.access_token.' . sha1($this->server->getBaseUrl() . '|master|backend');
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set(JwtTestFactory::buildJwtToken());
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);
    }
}
