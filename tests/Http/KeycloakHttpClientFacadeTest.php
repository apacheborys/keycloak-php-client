<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\AttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileGroupDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\ClientScopeManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\RealmSettingsManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\RoleManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\UserManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class KeycloakHttpClientFacadeTest extends TestCase
{
    public function testGetUsersDelegatesToUserManagement(): void
    {
        $dto = new SearchUsersDto(realm: 'master', username: 'user@example.com');
        $expectedUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $userManagement = $this->createMock(UserManagementHttpClientInterface::class);
        $userManagement
            ->expects(self::once())
            ->method('getUsers')
            ->with($dto)
            ->willReturn([$expectedUser]);

        $client = $this->createClient(
            userManagement: $userManagement,
        );

        self::assertSame([$expectedUser], $client->getUsers($dto));
    }

    public function testGetUserByIdDelegatesToUserManagement(): void
    {
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

        $userManagement = $this->createMock(UserManagementHttpClientInterface::class);
        $userManagement
            ->expects(self::once())
            ->method('getUserById')
            ->with($dto)
            ->willReturn($expectedUser);

        $client = $this->createClient(
            userManagement: $userManagement,
        );

        self::assertSame($expectedUser, $client->getUserById($dto));
    }

    public function testGetRolesDelegatesToRoleManagement(): void
    {
        $dto = new GetRolesDto(realm: 'master');
        $roleManagement = $this->createMock(RoleManagementHttpClientInterface::class);
        $roleManagement
            ->expects(self::once())
            ->method('getRoles')
            ->with($dto)
            ->willReturn([]);

        $client = $this->createClient(
            roleManagement: $roleManagement,
        );

        self::assertSame([], $client->getRoles($dto));
    }

    public function testCreateRoleDelegatesToRoleManagement(): void
    {
        $dto = new CreateRoleDto(
            realm: 'master',
            role: new \Apacheborys\KeycloakPhpClient\DTO\RoleDto(name: 'test-role'),
        );
        $roleManagement = $this->createMock(RoleManagementHttpClientInterface::class);
        $roleManagement
            ->expects(self::once())
            ->method('createRole')
            ->with($dto);

        $client = $this->createClient(
            roleManagement: $roleManagement,
        );
        $client->createRole($dto);
    }

    public function testClientScopeMethodsDelegateToClientScopeManagement(): void
    {
        $getDto = new GetClientScopesDto(realm: 'master');
        $getByIdDto = new GetClientScopeByIdDto(
            realm: 'master',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
        );
        $getProtocolMappersDto = new GetClientScopeProtocolMappersDto(
            realm: 'master',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
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
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
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
        $updateMapperDto = new UpdateClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            protocolMapperId: Uuid::fromString('3b1caa7b-dad7-4f43-9127-15969f303fe8'),
            protocolMapper: new ClientScopesProtocolMapperDto(
                id: Uuid::fromString('3b1caa7b-dad7-4f43-9127-15969f303fe8'),
                name: 'External user id attribute',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
            ),
        );
        $deleteMapperDto = new DeleteClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            protocolMapperId: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
        );
        $expectedById = new ClientScopeDto(
            id: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            name: 'backend-dedicated',
            protocol: 'openid-connect',
        );
        $expected = [
            new ClientScopeDto(
                name: 'backend-dedicated',
                protocol: 'openid-connect',
            ),
        ];
        $expectedProtocolMappers = [
            new ClientScopesProtocolMapperDto(
                name: 'External user id attribute',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
                config: [
                    'user.attribute' => 'external-user-id',
                ],
            ),
        ];

        $clientScopeManagement = $this->createMock(ClientScopeManagementHttpClientInterface::class);
        $clientScopeManagement
            ->expects(self::once())
            ->method('getClientScopes')
            ->with($getDto)
            ->willReturn($expected);
        $clientScopeManagement
            ->expects(self::once())
            ->method('createClientScope')
            ->with($createDto);
        $clientScopeManagement
            ->expects(self::once())
            ->method('createClientScopeProtocolMapper')
            ->with($createMapperDto);
        $clientScopeManagement
            ->expects(self::once())
            ->method('updateClientScopeProtocolMapper')
            ->with($updateMapperDto);
        $clientScopeManagement
            ->expects(self::once())
            ->method('deleteClientScopeProtocolMapper')
            ->with($deleteMapperDto);
        $clientScopeManagement
            ->expects(self::once())
            ->method('getClientScopeById')
            ->with($getByIdDto)
            ->willReturn($expectedById);
        $clientScopeManagement
            ->expects(self::once())
            ->method('getClientScopeProtocolMappers')
            ->with($getProtocolMappersDto)
            ->willReturn($expectedProtocolMappers);

        $client = $this->createClient(
            clientScopeManagement: $clientScopeManagement,
        );

        self::assertSame($expected, $client->getClientScopes($getDto));
        self::assertSame($expectedById, $client->getClientScopeById($getByIdDto));
        self::assertSame($expectedProtocolMappers, $client->getClientScopeProtocolMappers($getProtocolMappersDto));
        $client->createClientScope($createDto);
        $client->createClientScopeProtocolMapper($createMapperDto);
        $client->updateClientScopeProtocolMapper($updateMapperDto);
        $client->deleteClientScopeProtocolMapper($deleteMapperDto);
    }

    public function testGetOpenIdConfigurationDelegatesToOidcInteraction(): void
    {
        $expected = new OpenIdConfigurationDto(
            issuer: 'http://localhost:8080/realms/master',
            jwksUri: 'http://localhost:8080/realms/master/protocol/openid-connect/certs',
        );

        $oidcInteraction = $this->createMock(OidcInteractionHttpClientInterface::class);
        $oidcInteraction
            ->expects(self::once())
            ->method('getOpenIdConfiguration')
            ->with('master')
            ->willReturn($expected);

        $client = $this->createClient(
            oidcInteraction: $oidcInteraction,
        );

        self::assertSame($expected, $client->getOpenIdConfiguration('master'));
    }

    public function testGetUserProfileDelegatesToRealmSettingsManagement(): void
    {
        $dto = new GetUserProfileDto(realm: 'master');
        $expected = new UserProfileDto(
            attributes: [
                new AttributeDto(name: 'external-user-id'),
            ],
            groups: [
                new UserProfileGroupDto(name: 'user-metadata'),
            ],
        );

        $realmSettingsManagement = $this->createMock(RealmSettingsManagementHttpClientInterface::class);
        $realmSettingsManagement
            ->expects(self::once())
            ->method('getUserProfile')
            ->with($dto)
            ->willReturn($expected);

        $client = $this->createClient(
            realmSettingsManagement: $realmSettingsManagement,
        );

        self::assertSame($expected, $client->getUserProfile($dto));
    }

    public function testRequestTokenByPasswordDelegatesToOidcInteraction(): void
    {
        $dto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'user@example.com',
            password: 'password',
            grantType: OidcGrantType::PASSWORD,
        );
        $expected = new OidcTokenResponseDto(
            accessToken: JsonWebToken::fromRawToken(JwtTestFactory::buildJwtToken()),
            expiresIn: 3600,
            refreshExpiresIn: 0,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'email profile',
        );

        $oidcInteraction = $this->createMock(OidcInteractionHttpClientInterface::class);
        $oidcInteraction
            ->expects(self::once())
            ->method('requestTokenByPassword')
            ->with($dto)
            ->willReturn($expected);

        $client = $this->createClient(
            oidcInteraction: $oidcInteraction,
        );

        self::assertSame($expected, $client->requestTokenByPassword($dto));
    }

    private function createClient(
        ?UserManagementHttpClientInterface $userManagement = null,
        ?RoleManagementHttpClientInterface $roleManagement = null,
        ?ClientScopeManagementHttpClientInterface $clientScopeManagement = null,
        ?RealmSettingsManagementHttpClientInterface $realmSettingsManagement = null,
        ?OidcInteractionHttpClientInterface $oidcInteraction = null,
    ): KeycloakHttpClient {
        return new KeycloakHttpClient(
            userManagement: $userManagement ?? $this->createStub(UserManagementHttpClientInterface::class),
            roleManagement: $roleManagement ?? $this->createStub(RoleManagementHttpClientInterface::class),
            clientScopeManagement: $clientScopeManagement ?? $this->createStub(ClientScopeManagementHttpClientInterface::class),
            realmSettingsManagement: $realmSettingsManagement ?? $this->createStub(RealmSettingsManagementHttpClientInterface::class),
            oidcInteraction: $oidcInteraction ?? $this->createStub(OidcInteractionHttpClientInterface::class),
        );
    }
}
