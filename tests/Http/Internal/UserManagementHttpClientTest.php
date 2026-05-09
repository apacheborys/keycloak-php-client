<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakConflictException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\KeycloakPhpClient\Http\Internal\AccessTokenProvider;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Http\Internal\UserManagementHttpClient;
use Apacheborys\KeycloakPhpClient\Tests\Support\Cache\InMemoryCachePool;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleResponse;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStream;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Ramsey\Uuid\Uuid;

final class UserManagementHttpClientTest extends TestCase
{
    public function testGetUsersThrowsKeycloakAuthorizationExceptionFor403Response(): void
    {
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 403,
                body: json_encode(
                    [
                        'error' => 'access_denied',
                        'error_description' => 'Forbidden',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $this->expectException(KeycloakAuthorizationException::class);
        $client->getUsers(new SearchUsersDto(realm: 'master', email: 'user@example.com'));
    }

    public function testGetUserByIdThrowsKeycloakNotFoundExceptionFor404Response(): void
    {
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 404,
                body: json_encode(
                    [
                        'error' => 'not_found',
                        'error_description' => 'User not found',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $this->expectException(KeycloakNotFoundException::class);
        $client->getUserById(
            new GetUserByIdDto(
                realm: 'master',
                userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            ),
        );
    }

    public function testCreateUserThrowsKeycloakConflictExceptionFor409Response(): void
    {
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 409,
                body: json_encode(
                    [
                        'error' => 'user_exists',
                        'error_description' => 'User exists with same username',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        try {
            $client->createUser($this->buildCreateUserDto());
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakConflictException $exception) {
            self::assertSame(409, $exception->getContext()->getStatusCode());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Bearer', $exception->getMessage());
        }
    }

    public function testResetPasswordThrowsKeycloakTransportExceptionWithParsedError(): void
    {
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 400,
                body: json_encode(
                    [
                        'error' => 'No password provided',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        try {
            $client->resetPassword(
                new ResetUserPasswordDto(
                    realm: 'master',
                    user: $this->buildKeycloakUser(),
                    type: KeycloakCredentialType::password(),
                    value: 'SecretPassword!2026',
                    temporary: false,
                ),
            );
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakTransportException $exception) {
            self::assertSame('No password provided', $exception->getContext()->getKeycloakError());
            self::assertStringContainsString('error:', $exception->getMessage());
            self::assertStringNotContainsString('No password provided', $exception->getMessage());
            self::assertStringContainsString('No redacted provided', $exception->getMessage());
        }
    }

    public function testGetUsersThrowsKeycloakInvalidResponseExceptionForInvalidJson(): void
    {
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 200,
                body: '{"broken":',
            ),
        );

        try {
            $client->getUsers(new SearchUsersDto(realm: 'master', email: 'user@example.com'));
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakInvalidResponseException $exception) {
            self::assertSame(200, $exception->getContext()->getStatusCode());
            self::assertSame('{"broken":', $exception->getContext()->getResponseBody());
            self::assertStringContainsString('email=user%40example.com', $exception->getContext()->getUri());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Bearer', $exception->getMessage());
        }
    }

    public function testGetUsersThrowsKeycloakInvalidResponseExceptionForUnexpectedJsonObjectShape(): void
    {
        $body = json_encode(
            [
                'unexpected' => [
                    'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                ],
            ],
            JSON_THROW_ON_ERROR,
        );
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 200,
                body: $body,
            ),
        );

        try {
            $client->getUsers(new SearchUsersDto(realm: 'master', email: 'user@example.com'));
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakInvalidResponseException $exception) {
            self::assertSame($body, $exception->getContext()->getResponseBody());
            self::assertStringContainsString('/admin/realms/master/users', $exception->getContext()->getUri());
            self::assertStringContainsString('status 200', $exception->getMessage());
        }
    }

    public function testGetUserByIdThrowsKeycloakInvalidResponseExceptionForMissingRequiredField(): void
    {
        $userId = '92a372d5-c338-4e77-a1b3-08771241036e';
        $body = json_encode(
            [
                'id' => $userId,
                'createdTimestamp' => 1_700_000_000_000,
            ],
            JSON_THROW_ON_ERROR,
        );
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 200,
                body: $body,
            ),
        );

        try {
            $client->getUserById(
                new GetUserByIdDto(
                    realm: 'master',
                    userId: Uuid::fromString($userId),
                ),
            );
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakInvalidResponseException $exception) {
            self::assertSame($body, $exception->getContext()->getResponseBody());
            self::assertStringContainsString('/users/' . $userId, $exception->getContext()->getUri());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Bearer', $exception->getMessage());
        }
    }

    private function createClientWithResponse(SimpleResponse $response): UserManagementHttpClient
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $httpCore = new KeycloakHttpCore(
            baseUrl: 'https://keycloak.example',
            httpClient: $httpClient,
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
        );

        return new UserManagementHttpClient(
            httpCore: $httpCore,
            accessTokenProvider: $this->createAccessTokenProvider($httpCore),
        );
    }

    private function createAccessTokenProvider(KeycloakHttpCore $httpCore): AccessTokenProvider
    {
        $cache = new InMemoryCachePool();
        $cacheKey = 'keycloak.access_token.' . sha1($httpCore->getBaseUrl() . '|master|backend');
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->set(JwtTestFactory::buildJwtToken());
        $cacheItem->expiresAfter(3600);
        $cache->save($cacheItem);

        return new AccessTokenProvider(
            httpCore: $httpCore,
            clientRealm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            cache: $cache,
        );
    }

    private function buildCreateUserDto(): CreateUserDto
    {
        return new CreateUserDto(
            profile: new CreateUserProfileDto(
                username: 'user@example.com',
                email: 'user@example.com',
                emailVerified: true,
                enabled: true,
                firstName: 'User',
                lastName: 'Example',
                realm: 'master',
            ),
        );
    }

    private function buildKeycloakUser(): KeycloakUser
    {
        return KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ],
        );
    }

    private function createResponse(int $statusCode, string $body): SimpleResponse
    {
        return new SimpleResponse(
            statusCode: $statusCode,
            body: new SimpleStream($body),
        );
    }
}
