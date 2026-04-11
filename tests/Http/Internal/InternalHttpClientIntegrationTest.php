<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\AttributeDto;
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
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;
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
            'attributes' => [
                [
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
                    'name' => 'user-metadata',
                    'displayHeader' => 'User metadata',
                    'displayDescription' => 'Attributes, which refer to user metadata',
                ],
            ],
        ];

        $afterCreate = [
            'attributes' => [
                $initialProfile['attributes'][0],
                [
                    'name' => 'test_attribute',
                    'displayName' => 'Attribute for test reasons',
                    'validations' => [],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                    'multivalued' => false,
                    'annotations' => [],
                ],
            ],
            'groups' => $initialProfile['groups'],
        ];

        $afterUpdate = [
            'attributes' => [
                $initialProfile['attributes'][0],
                [
                    'name' => 'test_attribute',
                    'displayName' => 'Updated attribute',
                    'validations' => [],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                    'multivalued' => false,
                    'annotations' => [],
                ],
            ],
            'groups' => $initialProfile['groups'],
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
