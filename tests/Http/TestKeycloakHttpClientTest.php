<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileGroupDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use LogicException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class TestKeycloakHttpClientTest extends TestCase
{
    public function testQueuesResultsAndTracksCalls(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new SearchUsersDto(realm: 'master', email: 'user@example.com');

        $client->queueResult('getUsers', ['result']);

        self::assertSame(['result'], $client->getUsers($dto));
        self::assertSame(
            [
                [
                    'method' => 'getUsers',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testMissingQueueThrows(): void
    {
        $this->expectException(LogicException::class);

        $client = new TestKeycloakHttpClient();
        $client->getAvailableRealms();
    }

    public function testQueuedThrowableIsRethrown(): void
    {
        $this->expectException(RuntimeException::class);

        $client = new TestKeycloakHttpClient();
        $client->queueResult('getRoles', new RuntimeException('boom'));
        $client->getRoles(new GetRolesDto(realm: 'master'));
    }

    public function testCreateUserConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $profile = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: false,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
            attributes: [
                'external-user-id' => 'external-id-create',
            ],
        );
        $credential = new KeycloakCredential(
            type: KeycloakCredentialType::password(),
            credentialData: '{}',
            secretData: '{}',
            temporary: true,
        );
        $createUserDto = new CreateUserDto(profile: $profile, credentials: [$credential]);

        $client->queueResult('createUser', null);
        $client->createUser($createUserDto);

        self::assertSame(
            [
                [
                    'method' => 'createUser',
                    'args' => [$createUserDto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testGetUserByIdConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new GetUserByIdDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
        );
        $expectedUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $client->queueResult('getUserById', $expectedUser);

        self::assertSame($expectedUser, $client->getUserById($dto));
        self::assertSame(
            [
                [
                    'method' => 'getUserById',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testUpdateUserConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new UpdateUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'updated@example.com',
                firstName: 'Updated',
                attributes: [
                    'external-user-id' => 'external-id-update',
                ],
            ),
        );

        $client->queueResult('updateUser', null);
        $client->updateUser($dto);

        self::assertSame(
            [
                [
                    'method' => 'updateUser',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testRequestTokenByPasswordConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();

        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
        );
        $jwt = JwtTestFactory::buildJwtToken();

        $expected = new OidcTokenResponseDto(
            accessToken: JsonWebToken::fromRawToken($jwt),
            expiresIn: 3600,
            refreshExpiresIn: 0,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'email profile',
        );

        $client->queueResult('requestTokenByPassword', $expected);

        self::assertSame($expected, $client->requestTokenByPassword($dto));
        self::assertSame(
            [
                [
                    'method' => 'requestTokenByPassword',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testDeleteUserConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new DeleteUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
        );

        $client->queueResult('deleteUser', null);
        $client->deleteUser($dto);

        self::assertSame(
            [
                [
                    'method' => 'deleteUser',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testGetRolesConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $expected = [
            new RoleDto(name: 'admin', id: Uuid::fromString('7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8')),
            new RoleDto(name: 'user', id: Uuid::fromString('95e9532c-a85a-4548-81a2-8845d3e5e6f5')),
        ];
        $dto = new GetRolesDto(realm: 'master');

        $client->queueResult('getRoles', $expected);

        self::assertSame($expected, $client->getRoles($dto));
        self::assertSame(
            [
                [
                    'method' => 'getRoles',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testGetAvailableUserRolesConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $expected = [
            new RoleDto(name: 'admin', id: Uuid::fromString('7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8')),
        ];
        $dto = new GetUserAvailableRolesDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
        );

        $client->queueResult('getAvailableUserRoles', $expected);

        self::assertSame($expected, $client->getAvailableUserRoles($dto));
        self::assertSame(
            [
                [
                    'method' => 'getAvailableUserRoles',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testCreateRoleConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $role = new RoleDto(name: 'my-role', description: 'Role for test');
        $dto = new CreateRoleDto(
            realm: 'master',
            role: $role,
        );

        $client->queueResult('createRole', null);
        $client->createRole($dto);

        self::assertSame(
            [
                [
                    'method' => 'createRole',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testClientScopeMethodsConsumeQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $scopeId = Uuid::fromString('f480fece-9dc0-41e6-9a6a-ac25137d800e');

        $getDto = new GetClientScopesDto(realm: 'master');
        $getByIdDto = new GetClientScopeByIdDto(
            realm: 'master',
            clientScopeId: $scopeId,
        );
        $getProtocolMappersDto = new GetClientScopeProtocolMappersDto(
            realm: 'master',
            clientScopeId: $scopeId,
        );
        $createDto = new CreateClientScopeDto(
            realm: 'master',
            clientScope: new ClientScopeDto(
                name: 'test-client-scope',
                protocol: 'openid-connect',
            ),
        );
        $createMapperDto = new CreateClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: $scopeId,
            protocolMapper: new ClientScopesProtocolMapperDto(
                name: 'External user id attribute',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
                config: [
                    'claim.name' => 'external_user_id',
                    'user.attribute' => 'external-user-id',
                    'jsonType.label' => 'String',
                ],
            ),
        );
        $updateDto = new UpdateClientScopeDto(
            realm: 'master',
            clientScopeId: $scopeId,
            clientScope: new ClientScopeDto(
                id: $scopeId,
                name: 'test-client-scope-updated',
                protocol: 'openid-connect',
            ),
        );
        $updateMapperDto = new UpdateClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: $scopeId,
            protocolMapperId: Uuid::fromString('3b1caa7b-dad7-4f43-9127-15969f303fe8'),
            protocolMapper: new ClientScopesProtocolMapperDto(
                id: Uuid::fromString('3b1caa7b-dad7-4f43-9127-15969f303fe8'),
                name: 'External user id attribute',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
                config: [
                    'claim.name' => 'external_user_id_test',
                    'user.attribute' => 'external-user-id',
                    'jsonType.label' => 'String',
                ],
            ),
        );
        $deleteDto = new DeleteClientScopeDto(
            realm: 'master',
            clientScopeId: $scopeId,
        );
        $deleteMapperDto = new DeleteClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: $scopeId,
            protocolMapperId: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
        );

        $expected = [
            new ClientScopeDto(
                id: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
                name: 'backend-dedicated',
                protocol: 'openid-connect',
            ),
        ];
        $expectedProtocolMappers = [
            new ClientScopesProtocolMapperDto(
                id: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
                name: 'External user id attribute',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
                config: [
                    'user.attribute' => 'external-user-id',
                    'claim.name' => 'external_user_id',
                ],
            ),
        ];

        $client->queueResult('getClientScopes', $expected);
        $client->queueResult('getClientScopeById', $expected[0]);
        $client->queueResult('getClientScopeProtocolMappers', $expectedProtocolMappers);
        $client->queueResult('createClientScope', null);
        $client->queueResult('createClientScopeProtocolMapper', null);
        $client->queueResult('updateClientScope', null);
        $client->queueResult('updateClientScopeProtocolMapper', null);
        $client->queueResult('deleteClientScope', null);
        $client->queueResult('deleteClientScopeProtocolMapper', null);

        self::assertSame($expected, $client->getClientScopes($getDto));
        self::assertSame($expected[0], $client->getClientScopeById($getByIdDto));
        self::assertSame($expectedProtocolMappers, $client->getClientScopeProtocolMappers($getProtocolMappersDto));
        $client->createClientScope($createDto);
        $client->createClientScopeProtocolMapper($createMapperDto);
        $client->updateClientScope($updateDto);
        $client->updateClientScopeProtocolMapper($updateMapperDto);
        $client->deleteClientScope($deleteDto);
        $client->deleteClientScopeProtocolMapper($deleteMapperDto);

        self::assertSame(
            [
                [
                    'method' => 'getClientScopes',
                    'args' => [$getDto],
                ],
                [
                    'method' => 'getClientScopeById',
                    'args' => [$getByIdDto],
                ],
                [
                    'method' => 'getClientScopeProtocolMappers',
                    'args' => [$getProtocolMappersDto],
                ],
                [
                    'method' => 'createClientScope',
                    'args' => [$createDto],
                ],
                [
                    'method' => 'createClientScopeProtocolMapper',
                    'args' => [$createMapperDto],
                ],
                [
                    'method' => 'updateClientScope',
                    'args' => [$updateDto],
                ],
                [
                    'method' => 'updateClientScopeProtocolMapper',
                    'args' => [$updateMapperDto],
                ],
                [
                    'method' => 'deleteClientScope',
                    'args' => [$deleteDto],
                ],
                [
                    'method' => 'deleteClientScopeProtocolMapper',
                    'args' => [$deleteMapperDto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testDeleteRoleConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new DeleteRoleDto(
            realm: 'master',
            roleName: 'my-role',
        );

        $client->queueResult('deleteRole', null);
        $client->deleteRole($dto);

        self::assertSame(
            [
                [
                    'method' => 'deleteRole',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testAssignAndUnassignRolesConsumeQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $userId = '92a372d5-c338-4e77-a1b3-08771241036e';
        $roles = [
            new RoleDto(name: 'admin', id: Uuid::fromString('7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8')),
        ];
        $dto = new AssignUserRolesDto(
            realm: 'master',
            userId: Uuid::fromString($userId),
            roles: $roles,
        );

        $client->queueResult('assignRolesToUser', null);
        $client->queueResult('unassignRolesFromUser', null);

        $client->assignRolesToUser($dto);
        $client->unassignRolesFromUser($dto);

        self::assertSame(
            [
                [
                    'method' => 'assignRolesToUser',
                    'args' => [$dto],
                ],
                [
                    'method' => 'unassignRolesFromUser',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testGetOpenIdConfigurationConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $expected = new OpenIdConfigurationDto(
            issuer: 'http://localhost:8080/realms/master',
            jwksUri: 'http://localhost:8080/realms/master/protocol/openid-connect/certs',
        );

        $client->queueResult('getOpenIdConfiguration', $expected);

        self::assertSame($expected, $client->getOpenIdConfiguration('master'));
        self::assertSame(
            [
                [
                    'method' => 'getOpenIdConfiguration',
                    'args' => ['master', true],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testGetJwksConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $expected = new JwksDto(
            keys: [
                new JwkDto(
                    kty: 'RSA',
                    kid: 'kid',
                    use: 'sig',
                    alg: 'RS256',
                    n: 'modulus',
                    e: 'AQAB',
                    x5c: ['certificate'],
                ),
            ],
        );

        $client->queueResult('getJwks', $expected);

        self::assertSame(
            $expected,
            $client->getJwks('master', 'http://localhost:8080/realms/master/protocol/openid-connect/certs')
        );
        self::assertSame(
            [
                [
                    'method' => 'getJwks',
                    'args' => ['master', 'http://localhost:8080/realms/master/protocol/openid-connect/certs'],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testGetJwkConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $expected = new JwkDto(
            kty: 'RSA',
            kid: 'kid',
            use: 'sig',
            alg: 'RS256',
            n: 'modulus',
            e: 'AQAB',
            x5c: ['certificate'],
        );

        $client->queueResult('getJwk', $expected);

        self::assertSame(
            $expected,
            $client->getJwk('master', 'kid', 'http://localhost:8080/realms/master/protocol/openid-connect/certs')
        );
        self::assertSame(
            [
                [
                    'method' => 'getJwk',
                    'args' => ['master', 'kid', 'http://localhost:8080/realms/master/protocol/openid-connect/certs', true],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testRefreshTokenConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            refreshToken: 'refresh-token',
            grantType: OidcGrantType::REFRESH_TOKEN,
        );

        $expected = new OidcTokenResponseDto(
            accessToken: JsonWebToken::fromRawToken(JwtTestFactory::buildJwtToken()),
            expiresIn: 3600,
            refreshExpiresIn: 1800,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'email profile',
            refreshToken: 'refresh-token',
        );

        $client->queueResult('refreshToken', $expected);

        self::assertSame($expected, $client->refreshToken($dto));
        self::assertSame(
            [
                [
                    'method' => 'refreshToken',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testUserProfileMethodsConsumeQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $profile = new UserProfileDto(
            attributes: [
                new AttributeDto(name: 'external-user-id'),
            ],
            groups: [
                new UserProfileGroupDto(name: 'user-metadata'),
            ],
        );

        $getDto = new GetUserProfileDto(realm: 'master');
        $createDto = new CreateUserProfileAttributeDto(
            realm: 'master',
            attribute: new AttributeDto(name: 'test_attribute'),
        );
        $updateDto = new UpdateUserProfileAttributeDto(
            realm: 'master',
            attribute: new AttributeDto(name: 'test_attribute', displayName: 'Updated'),
        );
        $deleteDto = new DeleteUserProfileAttributeDto(
            realm: 'master',
            attributeName: 'test_attribute',
        );

        $client->queueResult('getUserProfile', $profile);
        $client->queueResult('createUserProfileAttribute', $profile);
        $client->queueResult('updateUserProfileAttribute', $profile);
        $client->queueResult('deleteUserProfileAttribute', $profile);

        self::assertSame($profile, $client->getUserProfile($getDto));
        self::assertSame($profile, $client->createUserProfileAttribute($createDto));
        self::assertSame($profile, $client->updateUserProfileAttribute($updateDto));
        self::assertSame($profile, $client->deleteUserProfileAttribute($deleteDto));

        self::assertSame(
            [
                [
                    'method' => 'getUserProfile',
                    'args' => [$getDto],
                ],
                [
                    'method' => 'createUserProfileAttribute',
                    'args' => [$createDto],
                ],
                [
                    'method' => 'updateUserProfileAttribute',
                    'args' => [$updateDto],
                ],
                [
                    'method' => 'deleteUserProfileAttribute',
                    'args' => [$deleteDto],
                ],
            ],
            $client->getCalls(),
        );
    }

}
