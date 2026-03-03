<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Service\KeycloakService;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestMapper;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestUser;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use LogicException;
use PHPUnit\Framework\TestCase;

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
        $service = new KeycloakService($httpClient, [$mapper]);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $httpClient->queueResult('createUser', null);
        $httpClient->queueResult('getUsers', [$createdUser]);
        $httpClient->queueResult('resetPassword', null);

        $result = $service->createUser($user, new PasswordDto(plainPassword: 'secret'));

        self::assertSame($createdUser, $result);
        self::assertSame(
            ['createUser', 'getUsers', 'resetPassword'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testUpdateUserDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedUpdateDto = new UpdateUserDto(
            realm: 'master',
            userId: '92a372d5-c338-4e77-a1b3-08771241036e',
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
        $service = new KeycloakService($httpClient, [$mapper]);
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

        $httpClient->queueResult('updateUser', null);
        $httpClient->queueResult('getUsers', [$updatedUser]);

        $result = $service->updateUser($oldUserVersion, $newUserVersion);

        self::assertSame($updatedUser, $result);
        self::assertSame(
            ['updateUser', 'getUsers'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        self::assertSame($mappedUpdateDto, $httpClient->getCalls()[0]['args'][0]);
        self::assertSame($oldUserVersion, $mapper->getCapturedOldUserForUpdate());
        self::assertSame($newUserVersion, $mapper->getCapturedNewUserForUpdate());
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
        $service = new KeycloakService($httpClient, [$mapper]);

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

        $service = new KeycloakService($httpClient, [$mapper]);
        $user = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');

        $httpClient->queueResult('deleteUser', null);
        $service->deleteUser($user);

        $calls = $httpClient->getCalls();
        self::assertCount(1, $calls);
        self::assertSame('deleteUser', $calls[0]['method']);
        self::assertSame('master', $calls[0]['args'][0]->getRealm());
        self::assertSame($user->getId(), $calls[0]['args'][0]->getUserId());
    }

    public function testGetAvailableRealmsDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = new KeycloakService($httpClient, [$mapper]);

        $httpClient->queueResult('getAvailableRealms', ['master']);

        self::assertSame(['master'], $service->getAvailableRealms());
    }

    public function testAuthenticateJwtCallsJwksAndThrows(): void
    {
        $this->expectException(LogicException::class);

        $httpClient = new TestKeycloakHttpClient();

        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto()
        );
        $service = new KeycloakService($httpClient, [$mapper]);

        $httpClient->queueResult('getJwks', []);

        $service->authenticateJwt('jwt', 'master');
    }

    public function testLoginUserDelegatesToHttpClient(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $tokenRequest = $this->buildTokenRequestDto();
        $plainPassword = 'SecretPassword!2026';

        $mapper = new ServiceTestMapper($this->buildProfileDto(), $tokenRequest);
        $service = new KeycloakService($httpClient, [$mapper]);
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

        $service = new KeycloakService($httpClient, [$mapper]);

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
}
