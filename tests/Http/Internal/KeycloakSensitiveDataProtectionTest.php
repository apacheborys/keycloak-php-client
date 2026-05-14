<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakErrorContext;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Http\Internal\OidcInteractionHttpClient;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18ClientException;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleResponse;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStream;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use Apacheborys\KeycloakPhpClient\Token\ClientCredentialsTokenProvider;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class KeycloakSensitiveDataProtectionTest extends TestCase
{
    public function testAuthenticationExceptionDoesNotLeakAuthorizationHeaderToken(): void
    {
        $core = $this->createCore($this->createStub(ClientInterface::class));
        $request = $core->createRequest(
            method: 'GET',
            endpoint: 'https://keycloak.example/admin/realms/master/users?safe=value',
            headers: ['Authorization' => 'Bearer very-secret-token'],
        );
        $response = $this->createResponse(
            statusCode: 401,
            body: json_encode(
                [
                    'error' => 'invalid_client',
                    'error_description' => 'Invalid client secret',
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        try {
            $core->assertSuccessfulResponse($request, $response);
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakAuthenticationException $exception) {
            self::assertStringNotContainsString('very-secret-token', $exception->getMessage());
            self::assertFalse(method_exists($exception->getContext(), 'getRequestHeaders'));
        }
    }

    public function testSensitiveQueryParametersAreRedactedInContextAndMessage(): void
    {
        $exception = new KeycloakTransportException(
            new KeycloakErrorContext(
                method: 'GET',
                uri: 'https://keycloak.example/admin/realms/master/users'
                    . '?client_secret=my-secret'
                    . '&refresh_token=rt'
                    . '&access_token=at'
                    . '&password=p'
                    . '&code=c'
                    . '&safe=value',
                statusCode: 400,
            ),
        );

        self::assertSame(
            'https://keycloak.example/admin/realms/master/users'
            . '?client_secret=[redacted]'
            . '&refresh_token=[redacted]'
            . '&access_token=[redacted]'
            . '&password=[redacted]'
            . '&code=[redacted]'
            . '&safe=value',
            $exception->getContext()->getUri(),
        );
        self::assertStringContainsString('safe=value', $exception->getMessage());
        self::assertStringContainsString('client_secret=[redacted]', $exception->getMessage());
        self::assertStringContainsString('refresh_token=[redacted]', $exception->getMessage());
        self::assertStringContainsString('access_token=[redacted]', $exception->getMessage());
        self::assertStringContainsString('password=[redacted]', $exception->getMessage());
        self::assertStringContainsString('code=[redacted]', $exception->getMessage());
        self::assertStringNotContainsString('client_secret=my-secret', $exception->getMessage());
        self::assertStringNotContainsString('refresh_token=rt', $exception->getMessage());
        self::assertStringNotContainsString('access_token=at', $exception->getMessage());
        self::assertStringNotContainsString('password=p', $exception->getMessage());
        self::assertStringNotContainsString('code=c', $exception->getMessage());
    }

    public function testKeycloakErrorResponseCanAppearWithoutRequestSecretValueLeak(): void
    {
        $exception = new KeycloakAuthenticationException(
            new KeycloakErrorContext(
                method: 'POST',
                uri: 'https://keycloak.example/realms/master/protocol/openid-connect/token'
                    . '?client_secret=my-secret&safe=value',
                statusCode: 401,
                keycloakError: 'invalid_client',
                keycloakErrorDescription: 'Invalid client secret',
            ),
        );

        self::assertStringContainsString('error: invalid_client', $exception->getMessage());
        self::assertStringContainsString('description: Invalid client secret', $exception->getMessage());
        self::assertStringContainsString('client_secret=[redacted]', $exception->getMessage());
        self::assertStringContainsString('safe=value', $exception->getMessage());
        self::assertStringNotContainsString('my-secret', $exception->getMessage());
    }

    public function testAccessTokenProviderFailureDoesNotCaptureRequestFormSecret(): void
    {
        $responseBody = json_encode(
            [
                'error' => 'invalid_client',
                'error_description' => 'Invalid client secret',
            ],
            JSON_THROW_ON_ERROR,
        );
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($this->createResponse(statusCode: 401, body: $responseBody));

        $core = $this->createCore($httpClient);
        $provider = new ClientCredentialsTokenProvider(
            oidcInteractionHttpClient: new OidcInteractionHttpClient(
                httpCore: $core,
            ),
            config: new KeycloakClientConfig(
                baseUrl: 'https://keycloak.example',
                clientRealm: 'master',
                clientId: 'backend',
                clientSecret: 'very-secret-client-secret',
            ),
        );

        try {
            $provider->getAccessToken();
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakAuthenticationException $exception) {
            self::assertStringNotContainsString('very-secret-client-secret', $exception->getMessage());
            self::assertSame($responseBody, $exception->getContext()->getResponseBody());
            self::assertFalse(method_exists($exception->getContext(), 'getRequestBody'));
        }
    }

    public function testTransportExceptionDoesNotLeakSensitiveUriValues(): void
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
                . '?client_secret=my-secret'
                . '&refresh_token=rt'
                . '&access_token=at'
                . '&password=p'
                . '&code=c'
                . '&safe=value',
            headers: ['Authorization' => 'Bearer very-secret-token'],
        );

        try {
            $core->sendRequest($request);
            self::fail('Expected exception was not thrown.');
        } catch (KeycloakTransportException $exception) {
            self::assertSame(
                'https://keycloak.example/realms/master/protocol/openid-connect/token'
                . '?client_secret=[redacted]'
                . '&refresh_token=[redacted]'
                . '&access_token=[redacted]'
                . '&password=[redacted]'
                . '&code=[redacted]'
                . '&safe=value',
                $exception->getContext()->getUri(),
            );
            self::assertStringContainsString('safe=value', $exception->getMessage());
            self::assertStringNotContainsString('very-secret-token', $exception->getMessage());
            self::assertStringNotContainsString('client_secret=my-secret', $exception->getMessage());
            self::assertStringNotContainsString('refresh_token=rt', $exception->getMessage());
            self::assertStringNotContainsString('access_token=at', $exception->getMessage());
            self::assertStringNotContainsString('password=p', $exception->getMessage());
            self::assertStringNotContainsString('code=c', $exception->getMessage());
        }
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
            body: new SimpleStream($body),
        );
    }
}
