<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Http\Internal\OidcInteractionHttpClient;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18ClientException;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleResponse;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStream;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class OidcInteractionHttpClientTokenErrorHandlingTest extends TestCase
{
    public function testInvalidClientCredentialsAreDistinguishedFromTransportFailuresWithoutLeakingSecrets(): void
    {
        $body = json_encode(
            [
                'error' => 'invalid_client',
                'error_description' => 'client_secret=top-secret password=SecretPassword!2026 '
                    . 'refresh_token=refresh-secret access_token=access-secret '
                    . 'Authorization: Bearer bearer-secret',
            ],
            JSON_THROW_ON_ERROR,
        );

        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 400,
                body: $body,
            ),
        );

        try {
            $client->requestToken(
                OidcTokenRequestDto::forClientCredentials(
                    realm: 'master',
                    clientId: 'backend',
                    clientSecret: 'top-secret',
                ),
            );
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakAuthenticationException $exception) {
            self::assertStringContainsString('invalid_client', $exception->getMessage());
            self::assertStringContainsString('[redacted]', $exception->getContext()->getResponseBody() ?? '');
            self::assertStringNotContainsString('top-secret', $exception->getMessage());
            self::assertStringNotContainsString('SecretPassword!2026', $exception->getMessage());
            self::assertStringNotContainsString('refresh-secret', $exception->getMessage());
            self::assertStringNotContainsString('access-secret', $exception->getMessage());
            self::assertStringNotContainsString('bearer-secret', $exception->getMessage());
            self::assertStringNotContainsString('top-secret', $exception->getContext()->getResponseBody() ?? '');
            self::assertStringNotContainsString(
                'SecretPassword!2026',
                $exception->getContext()->getKeycloakErrorDescription() ?? '',
            );
        }
    }

    public function testInvalidGrantBecomesTokenAcquisitionException(): void
    {
        $client = $this->createClientWithResponse(
            $this->createResponse(
                statusCode: 400,
                body: json_encode(
                    [
                        'error' => 'invalid_grant',
                        'error_description' => 'Invalid user credentials',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $this->expectException(KeycloakAuthenticationException::class);

        $client->requestToken(
            OidcTokenRequestDto::forPasswordGrant(
                realm: 'master',
                clientId: 'backend',
                clientSecret: 'secret',
                username: 'oleg@example.com',
                password: 'SecretPassword!2026',
            ),
        );
    }

    public function testTransportFailureDuringTokenAcquisitionRemainsTransportFailure(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willThrowException(new NativePsr18ClientException('connection failed'));

        $client = new OidcInteractionHttpClient(
            httpCore: $this->createCore($httpClient),
        );

        try {
            $client->requestToken(
                OidcTokenRequestDto::forClientCredentials(
                    realm: 'master',
                    clientId: 'backend',
                    clientSecret: 'top-secret',
                ),
            );
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakTransportException $exception) {
            self::assertStringNotContainsString('top-secret', $exception->getMessage());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Bearer', $exception->getMessage());
            self::assertFalse(method_exists($exception->getContext(), 'getRequestBody'));
        }
    }

    public function testMalformedTokenResponseRemainsInvalidResponseWithoutLeakingTokens(): void
    {
        $body = json_encode(
            [
                'access_token' => 'access-secret',
                'refresh_token' => 'refresh-secret',
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
            $client->requestToken(
                OidcTokenRequestDto::forClientCredentials(
                    realm: 'master',
                    clientId: 'backend',
                    clientSecret: 'top-secret',
                ),
            );
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakInvalidResponseException $exception) {
            self::assertStringNotContainsString('access-secret', $exception->getMessage());
            self::assertStringNotContainsString('refresh-secret', $exception->getMessage());
            self::assertStringNotContainsString('access-secret', $exception->getContext()->getResponseBody() ?? '');
            self::assertStringNotContainsString('refresh-secret', $exception->getContext()->getResponseBody() ?? '');
            self::assertStringContainsString('[redacted]', $exception->getContext()->getResponseBody() ?? '');
        }
    }

    private function createClientWithResponse(SimpleResponse $response): OidcInteractionHttpClient
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        return new OidcInteractionHttpClient(
            httpCore: $this->createCore($httpClient),
        );
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

    private function createResponse(int $statusCode, string $body): SimpleResponse
    {
        return new SimpleResponse(
            statusCode: $statusCode,
            headers: ['Content-Type' => ['application/json']],
            body: new SimpleStream($body),
        );
    }
}
