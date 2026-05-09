<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakConflictException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18ClientException;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleResponse;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStream;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class KeycloakHttpCoreTest extends TestCase
{
    #[DataProvider('responseExceptionProvider')]
    public function testAssertSuccessfulResponseMapsNon2xxStatusesToTypedExceptions(
        int $statusCode,
        string $expectedExceptionClass,
    ): void {
        $core = $this->createCore(
            $this->createStub(ClientInterface::class),
        );
        $request = $core->createRequest(
            method: 'POST',
            endpoint: 'https://keycloak.example/realms/master/protocol/openid-connect/token'
                . '?client_id=backend&client_secret=top-secret',
            headers: ['Authorization' => 'Bearer super-secret-token'],
        );
        $response = $this->createResponse(
            statusCode: $statusCode,
            body: json_encode(
                [
                    'error' => 'invalid_grant',
                    'error_description' => 'Invalid user credentials',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        try {
            $core->assertSuccessfulResponse($request, $response);
            self::fail('Expected exception was not thrown.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($expectedExceptionClass, $exception);
        }
    }

    public function testDecodeJsonResponseMapsInvalidJsonToKeycloakInvalidResponseException(): void
    {
        $core = $this->createCore(
            $this->createStub(ClientInterface::class),
        );
        $request = $core->createRequest(
            method: 'GET',
            endpoint: 'https://keycloak.example/admin/realms/master/users?client_secret=top-secret',
        );
        $response = $this->createResponse(
            statusCode: 200,
            body: '{"broken":',
        );

        try {
            $core->decodeJsonResponse($request, $response);
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakInvalidResponseException $exception) {
            self::assertSame(200, $exception->getContext()->getStatusCode());
            self::assertSame('GET', $exception->getContext()->getMethod());
            self::assertSame('{"broken":', $exception->getContext()->getResponseBody());
        }
    }

    public function testSendRequestWrapsPsr18TransportExceptionsWithoutLeakingSensitiveData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willThrowException(new NativePsr18ClientException('connection failed'));

        $core = $this->createCore($httpClient);
        $request = $core->createRequest(
            method: 'POST',
            endpoint: 'https://keycloak.example/realms/master/protocol/openid-connect/token'
                . '?client_id=backend&client_secret=top-secret',
            headers: ['Authorization' => 'Bearer super-secret-token'],
        );

        try {
            $core->sendRequest($request);
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakTransportException $exception) {
            self::assertSame('POST', $exception->getContext()->getMethod());
            self::assertNull($exception->getContext()->getStatusCode());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Bearer', $exception->getMessage());
            self::assertStringNotContainsString('client_secret', $exception->getMessage());
            self::assertStringNotContainsString(
                'top-secret',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                'client_secret',
                $exception->getContext()->getUri(),
            );
            self::assertStringContainsString(
                'client_id=backend',
                $exception->getContext()->getUri(),
            );
        }
    }

    public function testAssertSuccessfulResponseCapturesCorrelationId(): void
    {
        $core = $this->createCore(
            $this->createStub(ClientInterface::class),
        );
        $request = $core->createRequest(
            method: 'GET',
            endpoint: 'https://keycloak.example/admin/realms/master/users',
        );
        $response = $this->createResponse(
            statusCode: 500,
            body: json_encode(
                [
                    'error' => 'server_error',
                    'error_description' => 'Unexpected failure',
                ],
                JSON_THROW_ON_ERROR,
            ),
            headers: ['X-Correlation-ID' => ['req-123']],
        );

        try {
            $core->assertSuccessfulResponse($request, $response);
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakServerException $exception) {
            self::assertSame('req-123', $exception->getContext()->getCorrelationId());
        }
    }

    /**
     * @return iterable<string, array{int, class-string<\Throwable>}>
     */
    public static function responseExceptionProvider(): iterable
    {
        yield '401 authentication' => [401, KeycloakAuthenticationException::class];
        yield '403 authorization' => [403, KeycloakAuthorizationException::class];
        yield '404 not found' => [404, KeycloakNotFoundException::class];
        yield '409 conflict' => [409, KeycloakConflictException::class];
        yield '429 rate limit' => [429, KeycloakRateLimitException::class];
        yield '500 server' => [500, KeycloakServerException::class];
        yield '400 transport' => [400, KeycloakTransportException::class];
    }

    private function createCore(ClientInterface $httpClient): KeycloakHttpCore
    {
        return new KeycloakHttpCore(
            baseUrl: 'https://keycloak.example',
            httpClient: $httpClient,
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
        );
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function createResponse(
        int $statusCode,
        string $body,
        array $headers = [],
    ): SimpleResponse {
        return new SimpleResponse(
            statusCode: $statusCode,
            headers: $headers,
            body: new SimpleStream($body),
        );
    }
}
