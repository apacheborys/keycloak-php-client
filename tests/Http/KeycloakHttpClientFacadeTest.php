<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\RoleManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\UserManagementHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;

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
        ?OidcInteractionHttpClientInterface $oidcInteraction = null,
    ): KeycloakHttpClient {
        return new KeycloakHttpClient(
            userManagement: $userManagement ?? $this->createStub(UserManagementHttpClientInterface::class),
            roleManagement: $roleManagement ?? $this->createStub(RoleManagementHttpClientInterface::class),
            oidcInteraction: $oidcInteraction ?? $this->createStub(OidcInteractionHttpClientInterface::class),
        );
    }
}
