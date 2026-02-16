<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
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
        $client->getRoles();
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
