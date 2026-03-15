<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceFactory;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestMapper;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestUser;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use LogicException;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class KeycloakServiceTest extends TestCase
{
    public function testCreateUserWithPlainPasswordResetsPasswordAndReturnsUser(): void
    {
        $httpClient = new TestKeycloakHttpClient();

        $profileDto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );

        $loginDto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'user@example.com',
            password: 'secret',
            grantType: OidcGrantType::PASSWORD,
        );

        $mapper = new ServiceTestMapper($profileDto, $loginDto);
        $service = $this->createService($httpClient, $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('createUser', null);
        $httpClient->queueResult('getUsers', [$createdUser]);
        $httpClient->queueResult('getUsers', [$createdUser]);
        $httpClient->queueResult('resetPassword', null);

        $result = $service->createUser($user, new PasswordDto(plainPassword: 'secret'));

        self::assertSame($createdUser, $result);
        self::assertSame(
            ['getRoles', 'createUser', 'getUsers', 'resetPassword', 'getRoles', 'getUsers'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testUpdateUserDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedUpdateDto = new UpdateUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'new@example.com',
                firstName: 'New',
            ),
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserDto: $mappedUpdateDto
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUserVersion = new ServiceTestUser(
            id: '92a372d5-c338-4e77-a1b3-08771241036e',
            email: 'old@example.com',
            firstName: 'Old',
        );
        $newUserVersion = new ServiceTestUser(
            id: '92a372d5-c338-4e77-a1b3-08771241036e',
            email: 'new@example.com',
            firstName: 'New',
        );
        $updatedUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'email' => 'new@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('updateUser', null);
        $httpClient->queueResult('getUsers', [$updatedUser]);
        $httpClient->queueResult('getUsers', [$updatedUser]);

        $result = $service->updateUser($oldUserVersion, $newUserVersion);

        self::assertSame($updatedUser, $result);
        self::assertSame(
            ['getRoles', 'updateUser', 'getUsers', 'getRoles', 'getUsers'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        self::assertSame($mappedUpdateDto, $httpClient->getCalls()[1]['args'][0]);
        self::assertSame($oldUserVersion, $mapper->getCapturedOldUserForUpdate());
        self::assertSame($newUserVersion, $mapper->getCapturedNewUserForUpdate());
    }

    public function testCreateUserCreatesMissingRolesAndAssignsThemToUser(): void
    {
        $httpClient = new TestKeycloakHttpClient();

        $profileDto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
            roles: [
                new RoleDto(name: 'existing-role'),
                new RoleDto(name: 'missing-role', description: 'Role for test'),
            ],
        );
        $mapper = new ServiceTestMapper($profileDto, $this->buildTokenRequestDto());
        $service = $this->createService($httpClient, $mapper, true);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );
        $existingRole = new RoleDto(
            id: Uuid::fromString('7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8'),
            name: 'existing-role',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );
        $missingRole = new RoleDto(
            id: Uuid::fromString('3e7f40af-e8d4-4ead-bb8b-b034e95ffad8'),
            name: 'missing-role',
            description: 'Role for test',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$existingRole]);
        $httpClient->queueResult('getRoles', [$existingRole]);
        $httpClient->queueResult('getRoles', [$existingRole, $missingRole]);
        $httpClient->queueResult('createUser', null);
        $httpClient->queueResult('getUsers', [$createdUser]);
        $httpClient->queueResult('getUsers', [$createdUser]);
        $httpClient->queueResult('resetPassword', null);
        $httpClient->queueResult('createRole', null);
        $httpClient->queueResult('assignRolesToUser', null);

        $result = $service->createUser($user, new PasswordDto(plainPassword: 'secret'));

        self::assertSame($createdUser, $result);
        self::assertSame(
            [
                'getRoles',
                'createUser',
                'getUsers',
                'resetPassword',
                'getRoles',
                'createRole',
                'getRoles',
                'assignRolesToUser',
                'getUsers',
            ],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        self::assertSame('missing-role', $httpClient->getCalls()[5]['args'][0]->getRole()->getName());
        /** @var AssignUserRolesDto $assignRolesDto */
        $assignRolesDto = $httpClient->getCalls()[7]['args'][0];
        self::assertSame(
            [$existingRole, $missingRole],
            $assignRolesDto->getRoles(),
        );
    }

    public function testCreateUserWithMissingRoleThrowsWhenRoleCreationDisabled(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Role "missing-role" cannot be resolved in Keycloak available roles.');

        $httpClient = new TestKeycloakHttpClient();
        $profileDto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
            roles: [new RoleDto(name: 'missing-role')],
        );
        $mapper = new ServiceTestMapper($profileDto, $this->buildTokenRequestDto());
        $service = $this->createService($httpClient, $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('createUser', null);
        $httpClient->queueResult('getUsers', [$createdUser]);
        $httpClient->queueResult('resetPassword', null);

        $service->createUser($user, new PasswordDto(plainPassword: 'secret'));
    }

    public function testUpdateUserSynchronizesRoleMappings(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedUpdateDto = new UpdateUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'new@example.com',
                firstName: 'New',
                roles: [new RoleDto(name: 'role-new')],
            ),
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserDto: $mappedUpdateDto
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUserVersion = new ServiceTestUser(
            id: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: ['role-old'],
        );
        $newUserVersion = new ServiceTestUser(
            id: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: ['role-new'],
        );
        $updatedUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'email' => 'new@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );
        $roleOld = new RoleDto(
            id: Uuid::fromString('e95d307d-ef1c-4151-8d4b-11376ef7e307'),
            name: 'role-old',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );
        $roleNew = new RoleDto(
            id: Uuid::fromString('246657bd-17c7-4f9d-9ecf-98920f099ad6'),
            name: 'role-new',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$roleOld, $roleNew]);
        $httpClient->queueResult('getRoles', [$roleOld, $roleNew]);
        $httpClient->queueResult('updateUser', null);
        $httpClient->queueResult('getUsers', [$updatedUser]);
        $httpClient->queueResult('getUsers', [$updatedUser]);
        $httpClient->queueResult('getAvailableUserRoles', [$roleNew]);
        $httpClient->queueResult('assignRolesToUser', null);
        $httpClient->queueResult('unassignRolesFromUser', null);

        $result = $service->updateUser($oldUserVersion, $newUserVersion);

        self::assertSame($updatedUser, $result);
        self::assertSame(
            [
                'getRoles',
                'updateUser',
                'getUsers',
                'getRoles',
                'getAvailableUserRoles',
                'assignRolesToUser',
                'unassignRolesFromUser',
                'getUsers',
            ],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        /** @var AssignUserRolesDto $assignRolesToUserDto */
        $assignRolesToUserDto = $httpClient->getCalls()[5]['args'][0];
        /** @var AssignUserRolesDto $unassignRolesFromUserDto */
        $unassignRolesFromUserDto = $httpClient->getCalls()[6]['args'][0];
        self::assertSame([$roleNew], $assignRolesToUserDto->getRoles());
        self::assertSame([$roleOld], $unassignRolesFromUserDto->getRoles());
    }

    public function testUpdateUserFailsWhenDesiredRoleIsNotAssignableForUser(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Role "role-new" cannot be resolved in Keycloak available roles.');

        $httpClient = new TestKeycloakHttpClient();
        $mappedUpdateDto = new UpdateUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'new@example.com',
                roles: [new RoleDto(name: 'role-new')],
            ),
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserDto: $mappedUpdateDto
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUserVersion = new ServiceTestUser(
            id: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: [],
        );
        $newUserVersion = new ServiceTestUser(
            id: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: ['role-new'],
        );
        $roleNew = new RoleDto(
            id: Uuid::fromString('246657bd-17c7-4f9d-9ecf-98920f099ad6'),
            name: 'role-new',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $updatedUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'email' => 'new@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $httpClient->queueResult('getRoles', [$roleNew]);
        $httpClient->queueResult('getRoles', [$roleNew]);
        $httpClient->queueResult('updateUser', null);
        $httpClient->queueResult('getUsers', [$updatedUser]);
        $httpClient->queueResult('getAvailableUserRoles', []);

        $service->updateUser($oldUserVersion, $newUserVersion);
    }

    public function testUpdateUserRejectsDifferentIds(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Old and new user versions must reference the same user id.');

        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = $this->createService($httpClient, $mapper);

        $oldUserVersion = new ServiceTestUser(id: '92a372d5-c338-4e77-a1b3-08771241036e');
        $newUserVersion = new ServiceTestUser(id: 'd15f15d4-c107-4a99-8281-8b2a7d7c6d6a');

        $service->updateUser($oldUserVersion, $newUserVersion);
    }

    public function testDeleteUserUsesMapperAndHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();

        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );

        $service = $this->createService($httpClient, $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $httpClient->queueResult('deleteUser', null);
        $service->deleteUser($user);

        $calls = $httpClient->getCalls();
        self::assertCount(1, $calls);
        self::assertSame('deleteUser', $calls[0]['method']);
        self::assertSame('master', $calls[0]['args'][0]->getRealm());
        self::assertSame($user->getId(), $calls[0]['args'][0]->getUserId()->toString());
    }

    public function testGetAvailableRealmsDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = $this->createService($httpClient, $mapper);

        $httpClient->queueResult('getAvailableRealms', ['master']);

        self::assertSame(['master'], $service->getAvailableRealms());
    }

    public function testVerifyJwtReturnsFalseForMalformedToken(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = $this->createService($httpClient, $mapper);

        self::assertFalse($service->verifyJwt('malformed.jwt.token'));
        self::assertSame([], $httpClient->getCalls());
    }

    public function testVerifyJwtReturnsTrueForValidToken(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = $this->createService($httpClient, $mapper);

        $bundle = $this->buildSignedTokenAndJwks(realm: 'master');
        $httpClient->queueResult(
            'getOpenIdConfiguration',
            new OpenIdConfigurationDto(
                issuer: 'http://localhost:8080/realms/master',
                jwksUri: 'http://localhost:8080/realms/master/protocol/openid-connect/certs',
            )
        );
        $httpClient->queueResult('getJwk', $bundle['jwks']->findByKid(kid: 'test-kid'));

        self::assertTrue($service->verifyJwt($bundle['jwt']));
        self::assertSame(
            [
                [
                    'method' => 'getOpenIdConfiguration',
                    'args' => ['master', true],
                ],
                [
                    'method' => 'getJwk',
                    'args' => [
                        'master',
                        'test-kid',
                        'http://localhost:8080/realms/master/protocol/openid-connect/certs',
                        true,
                    ],
                ],
            ],
            $httpClient->getCalls(),
        );
    }

    public function testVerifyJwtReturnsFalseWhenOpenIdConfigurationRequestFails(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = $this->createService($httpClient, $mapper);

        $bundle = $this->buildSignedTokenAndJwks(realm: 'master');
        $httpClient->queueResult(
            'getOpenIdConfiguration',
            new RuntimeException('OpenID configuration is unavailable')
        );

        self::assertFalse($service->verifyJwt($bundle['jwt']));
        self::assertSame(
            [
                [
                    'method' => 'getOpenIdConfiguration',
                    'args' => ['master', true],
                ],
            ],
            $httpClient->getCalls(),
        );
    }

    public function testLoginUserDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $tokenRequest = $this->buildTokenRequestDto();
        $plainPassword = 'SecretPassword!2026';

        $mapper = new ServiceTestMapper($this->buildProfileDto(), $tokenRequest);
        $service = $this->createService($httpClient, $mapper);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $httpClient->queueResult('requestTokenByPassword', $this->buildTokenResponseDto());

        $result = $service->loginUser($user, $plainPassword);
        self::assertInstanceOf(OidcTokenResponseDto::class, $result);
        self::assertSame('requestTokenByPassword', $httpClient->getCalls()[0]['method']);
        self::assertSame($plainPassword, $mapper->getCapturedPlainPassword());
    }

    public function testRefreshTokenDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();

        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );

        $service = $this->createService($httpClient, $mapper);

        $refreshDto = new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            refreshToken: 'refresh-token',
            grantType: OidcGrantType::REFRESH_TOKEN,
        );

        $httpClient->queueResult('refreshToken', $this->buildTokenResponseDto());

        $result = $service->refreshToken($refreshDto);
        self::assertInstanceOf(OidcTokenResponseDto::class, $result);
        self::assertSame('refreshToken', $httpClient->getCalls()[0]['method']);
    }

    private function buildProfileDto(): CreateUserProfileDto
    {
        return new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
    }

    private function buildTokenRequestDto(): OidcTokenRequestDto
    {
        return new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'user@example.com',
            password: 'secret',
            grantType: OidcGrantType::PASSWORD,
        );
    }

    private function buildTokenResponseDto(): OidcTokenResponseDto
    {
        return new OidcTokenResponseDto(
            accessToken: JsonWebToken::fromRawToken(JwtTestFactory::buildJwtToken()),
            expiresIn: 3600,
            refreshExpiresIn: 0,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'email profile',
        );
    }

    /**
     * @return array{jwt: string, jwks: JwksDto}
     */
    private function buildSignedTokenAndJwks(string $realm): array
    {
        $privateKey = openssl_pkey_new(
            [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]
        );
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);

        $certificateBody = $this->buildCertificateBody(privateKey: $privateKey);
        $keyDetails = openssl_pkey_get_details(key: $privateKey);
        self::assertIsArray($keyDetails);
        self::assertArrayHasKey('rsa', $keyDetails);
        self::assertIsArray($keyDetails['rsa']);
        self::assertArrayHasKey('n', $keyDetails['rsa']);
        self::assertArrayHasKey('e', $keyDetails['rsa']);
        self::assertIsString($keyDetails['rsa']['n']);
        self::assertIsString($keyDetails['rsa']['e']);

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-kid',
        ];
        $payload = [
            'exp' => time() + 3600,
            'iat' => time(),
            'jti' => 'f9b4b801-bb78-4167-be60-b42d453332e7',
            'iss' => 'http://localhost:8080/realms/' . $realm,
            'aud' => ['account'],
            'sub' => '92a372d5-c338-4e77-a1b3-08771241036e',
            'typ' => 'Bearer',
            'azp' => 'backend',
            'acr' => 1,
            'realm_access' => ['roles' => ['role']],
            'resource_access' => [
                'backend' => ['roles' => ['role']],
                'account' => ['roles' => ['role']],
            ],
            'scope' => 'email profile',
            'email_verified' => true,
            'clientHost' => '127.0.0.1',
            'preferred_username' => 'user@example.com',
            'clientAddress' => '127.0.0.1',
            'client_id' => 'backend',
        ];

        $headerEncoded = $this->base64UrlEncode(value: json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(value: json_encode($payload, JSON_THROW_ON_ERROR));

        $signingInput = $headerEncoded . '.' . $payloadEncoded;
        $signature = '';
        $isSigned = openssl_sign(
            data: $signingInput,
            signature: $signature,
            private_key: $privateKey,
            algorithm: OPENSSL_ALGO_SHA256
        );
        self::assertTrue($isSigned);

        $jwt = $signingInput . '.' . $this->base64UrlEncode(value: $signature);
        $jwks = new JwksDto(
            keys: [
                new JwkDto(
                    kty: 'RSA',
                    kid: 'test-kid',
                    use: 'sig',
                    alg: 'RS256',
                    n: $this->base64UrlEncode(value: $keyDetails['rsa']['n']),
                    e: $this->base64UrlEncode(value: $keyDetails['rsa']['e']),
                    x5c: [$certificateBody],
                ),
            ],
        );

        return [
            'jwt' => $jwt,
            'jwks' => $jwks,
        ];
    }

    private function buildCertificateBody(OpenSSLAsymmetricKey $privateKey): string
    {
        $csr = openssl_csr_new(
            distinguished_names: ['commonName' => 'localhost'],
            private_key: $privateKey,
            options: ['digest_alg' => 'sha256']
        );
        self::assertNotFalse($csr);

        $certificate = openssl_csr_sign(
            csr: $csr,
            ca_certificate: null,
            private_key: $privateKey,
            days: 1,
            options: ['digest_alg' => 'sha256']
        );
        self::assertNotFalse($certificate);

        $certificatePem = '';
        $isExported = openssl_x509_export(certificate: $certificate, output: $certificatePem);
        self::assertTrue($isExported);

        $certificateBody = preg_replace(
            pattern: '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\\s+/',
            replacement: '',
            subject: $certificatePem
        );
        self::assertIsString($certificateBody);
        self::assertNotSame('', $certificateBody);

        return $certificateBody;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function createService(
        TestKeycloakHttpClient $httpClient,
        ServiceTestMapper $mapper,
        bool $isRoleCreationAllowed = false,
    ): KeycloakServiceInterface {
        $factory = new KeycloakServiceFactory();

        return $factory->create(
            httpClient: $httpClient,
            mappers: [$mapper],
            isRoleCreationAllowed: $isRoleCreationAllowed,
        );
    }
}
