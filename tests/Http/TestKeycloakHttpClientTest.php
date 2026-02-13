<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
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

    public function testLoginUserConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();

        $dto = new LoginUserDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'oleg@example.com',
            password: 'Roadsurfer!2026',
        );
        $jwt = $this->buildJwtToken();

        $expected = new RequestAccessDto(
            accessToken: JsonWebToken::fromRawToken($jwt),
            expiresIn: 3600,
            refreshExpiresIn: 0,
            tokenType: 'Bearer',
            nonBeforePolicy: 0,
            scope: 'email profile',
        );

        $client->queueResult('loginUser', $expected);

        self::assertSame($expected, $client->loginUser($dto));
        self::assertSame(
            [
                [
                    'method' => 'loginUser',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    private function buildJwtToken(): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'kid',
        ];
        $payload = [
            'exp' => time() + 3600,
            'iat' => time(),
            'jti' => 'f9b4b801-bb78-4167-be60-b42d453332e7',
            'iss' => 'http://localhost:8080/realms/master',
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
            'preferred_username' => 'oleg@example.com',
            'clientAddress' => '127.0.0.1',
            'client_id' => 'backend',
        ];

        return $this->base64UrlEncode($header) . '.' .
            $this->base64UrlEncode($payload) . '.' .
            $this->base64UrlEncode(['sig' => 'signature']);
    }

    private function base64UrlEncode(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
