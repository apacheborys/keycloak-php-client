<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Exception;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakConflictException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakErrorContext;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeycloakExceptionTest extends TestCase
{
    #[DataProvider('exceptionClassProvider')]
    public function testEveryExceptionCanBeInstantiatedWithContext(
        string $exceptionClass,
        ?int $statusCode,
    ): void {
        $context = $this->buildContext($statusCode);

        $exception = new $exceptionClass($context);

        self::assertInstanceOf(KeycloakException::class, $exception);
        self::assertSame($context, $exception->getContext());
        self::assertSame($statusCode ?? 0, $exception->getCode());
    }

    #[DataProvider('exceptionClassProvider')]
    public function testMessageIncludesMethodStatusAndSafeUri(
        string $exceptionClass,
        ?int $statusCode,
    ): void {
        $message = (new $exceptionClass($this->buildContext($statusCode)))->getMessage();

        self::assertStringContainsString('POST', $message);
        self::assertStringContainsString(
            'https://keycloak.example/realms/master/protocol/openid-connect/token',
            $message,
        );
        self::assertStringContainsString('client_id=backend', $message);
        self::assertStringContainsString('client_secret=[redacted]', $message);
        self::assertStringContainsString('refresh_token=[redacted]', $message);
        self::assertStringContainsString('access_token=[redacted]', $message);
        self::assertStringContainsString('password=[redacted]', $message);
        self::assertStringContainsString('scope=openid%20email', $message);
        self::assertStringContainsString('status', $message);
        self::assertStringContainsString(
            $statusCode !== null ? (string) $statusCode : 'unavailable',
            $message,
        );
        self::assertStringContainsString('error: invalid_client', $message);
        self::assertStringContainsString('description:', $message);
    }

    #[DataProvider('exceptionClassProvider')]
    public function testMessageDoesNotIncludeSensitiveFields(
        string $exceptionClass,
        ?int $statusCode,
    ): void {
        $message = (new $exceptionClass($this->buildContext($statusCode)))->getMessage();

        self::assertStringNotContainsString('Authorization', $message);
        self::assertStringNotContainsString('Bearer', $message);
        self::assertStringNotContainsString('top-secret', $message);
        self::assertStringNotContainsString('refresh-secret', $message);
        self::assertStringNotContainsString('access-secret', $message);
        self::assertStringNotContainsString('SuperSecretPassword!2026', $message);
    }

    public function testContextValuesAreAccessible(): void
    {
        $responseBody = json_encode(
            [
                'access_token' => 'access-secret',
                'refresh_token' => 'refresh-secret',
                'detail' => 'full response for diagnostics',
            ],
            JSON_THROW_ON_ERROR,
        );

        $context = new KeycloakErrorContext(
            method: 'post',
            uri: 'https://keycloak.example/admin/realms/master/users'
                . '?client_id=backend&client_secret=top-secret&exact=true',
            statusCode: 409,
            responseBody: $responseBody,
            keycloakError: 'user_exists',
            keycloakErrorDescription: 'Password policy violation',
            correlationId: 'req-123',
        );

        self::assertSame('POST', $context->getMethod());
        self::assertSame(
            'https://keycloak.example/admin/realms/master/users'
            . '?client_id=backend&client_secret=[redacted]&exact=true',
            $context->getUri(),
        );
        self::assertSame(409, $context->getStatusCode());
        self::assertSame($responseBody, $context->getResponseBody());
        self::assertSame('user_exists', $context->getKeycloakError());
        self::assertSame(
            'Password policy violation',
            $context->getKeycloakErrorDescription(),
        );
        self::assertSame('req-123', $context->getCorrelationId());
    }

    /**
     * @return iterable<string, array{class-string<KeycloakException>, ?int}>
     */
    public static function exceptionClassProvider(): iterable
    {
        yield 'base' => [KeycloakException::class, 400];
        yield 'transport' => [KeycloakTransportException::class, null];
        yield 'authentication' => [KeycloakAuthenticationException::class, 401];
        yield 'authorization' => [KeycloakAuthorizationException::class, 403];
        yield 'not-found' => [KeycloakNotFoundException::class, 404];
        yield 'conflict' => [KeycloakConflictException::class, 409];
        yield 'rate-limit' => [KeycloakRateLimitException::class, 429];
        yield 'server' => [KeycloakServerException::class, 503];
        yield 'invalid-response' => [KeycloakInvalidResponseException::class, 502];
    }

    private function buildContext(?int $statusCode): KeycloakErrorContext
    {
        return new KeycloakErrorContext(
            method: 'post',
            uri: 'https://keycloak.example/realms/master/protocol/openid-connect/token'
                . '?client_id=backend'
                . '&client_secret=top-secret'
                . '&refresh_token=refresh-secret'
                . '&access_token=access-secret'
                . '&password=SuperSecretPassword%212026'
                . '&scope=openid%20email',
            statusCode: $statusCode,
            responseBody: json_encode(
                [
                    'access_token' => 'access-secret',
                    'refresh_token' => 'refresh-secret',
                ],
                JSON_THROW_ON_ERROR,
            ),
            keycloakError: 'invalid_client',
            keycloakErrorDescription: 'Authorization: Bearer token client_secret '
                . 'and password were rejected.',
            correlationId: 'req-456',
        );
    }
}
