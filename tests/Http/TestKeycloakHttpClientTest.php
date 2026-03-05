<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use LogicException;
use PHPUnit\Framework\TestCase;
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

    public function testUpdateUserConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new UpdateUserDto(
            realm: 'master',
            userId: '92a372d5-c338-4e77-a1b3-08771241036e',
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'updated@example.com',
                firstName: 'Updated',
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
            userId: '92a372d5-c338-4e77-a1b3-08771241036e',
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
            new RoleDto(name: 'admin', id: '7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8'),
            new RoleDto(name: 'user', id: '95e9532c-a85a-4548-81a2-8845d3e5e6f5'),
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
            new RoleDto(name: 'admin', id: '7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8'),
        ];
        $dto = new GetUserAvailableRolesDto(
            realm: 'master',
            userId: '92a372d5-c338-4e77-a1b3-08771241036e',
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
            new RoleDto(name: 'admin', id: '7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8'),
        ];
        $dto = new AssignUserRolesDto(
            realm: 'master',
            userId: $userId,
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

}
