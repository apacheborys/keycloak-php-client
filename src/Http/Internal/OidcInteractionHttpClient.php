<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakConflictException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakErrorContext;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Token\AccessTokenProviderInterface;
use Assert\Assert;
use Assert\InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use TypeError;

final readonly class OidcInteractionHttpClient implements OidcInteractionHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private ?AccessTokenProviderInterface $accessTokenProvider = null,
    ) {
    }

    #[\Override]
    public function getOpenIdConfiguration(string $realm): OpenIdConfigurationDto
    {
        $endpoint = $this->httpCore->buildEndpoint(path: '/realms/' . $realm . '/.well-known/openid-configuration');
        $request = $this->httpCore->createRequest(method: 'GET', endpoint: $endpoint);

        $response = $this->httpCore->sendRequest(request: $request);
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static fn (array $data): OpenIdConfigurationDto => OpenIdConfigurationDto::fromArray(data: $data),
        );
    }

    #[\Override]
    public function getJwk(
        string $kid,
        string $jwksUri,
    ): ?JwkDto {
        $jwks = $this->getJwks(
            jwksUri: $jwksUri,
        );

        return $jwks->findByKid(kid: $kid);
    }

    #[\Override]
    public function getJwks(string $jwksUri): JwksDto
    {
        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $jwksUri,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static fn (array $data): JwksDto => JwksDto::fromArray(data: $data),
        );
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[\Override]
    public function getAvailableRealms(): array
    {
        if ($this->accessTokenProvider === null) {
            throw new LogicException('Access token provider is required for admin realm listing.');
        }

        $token = $this->accessTokenProvider->getAccessToken();
        $parameters = [
            'briefRepresentation' => 'true',
        ];

        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms',
            query: http_build_query(
                data: $parameters,
                arg_separator: '&',
                encoding_type: PHP_QUERY_RFC3986
            ),
        );

        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static function (array $data): array {
                Assert::that(array_is_list($data))->true();

                $realms = [];
                foreach ($data as $realmData) {
                    Assert::that($realmData)->isArray();
                    /** @var array<string, mixed> $realmData */
                    $realms[] = KeycloakRealm::fromArray(data: $realmData);
                }

                return $realms;
            },
        );
    }

    #[\Override]
    public function requestToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/realms/' . $dto->getRealm() . '/protocol/openid-connect/token'
        );

        $payload = http_build_query(
            data: $dto->toFormParams(),
            numeric_prefix: '',
            arg_separator: '&',
            encoding_type: PHP_QUERY_RFC3986
        );

        $request = $this->httpCore->createRequest(
            method: 'POST',
            endpoint: $endpoint,
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        return $this->mapTokenResponse(
            request: $request,
            response: $response,
        );
    }

    private function mapTokenResponse(
        RequestInterface $request,
        ResponseInterface $response,
    ): OidcTokenResponseDto {
        $body = (string) $response->getBody();
        $context = $this->createTokenResponseContext(
            request: $request,
            response: $response,
            responseBody: $body,
        );
        $statusCode = $response->getStatusCode();

        if ($statusCode === 400) {
            $responseData = $this->decodeTokenResponseBody(
                body: $body,
                context: $context,
            );

            $oauthError = isset($responseData['error']) && is_string($responseData['error'])
                ? $responseData['error']
                : null;

            if (in_array($oauthError, ['invalid_grant', 'unauthorized_client'], true)) {
                throw new KeycloakAuthenticationException($context);
            }

            if ($oauthError === 'invalid_client') {
                throw new KeycloakAuthenticationException($context);
            }

            throw new KeycloakTransportException($context);
        }

        $this->throwForUnsuccessfulTokenStatusCode(
            statusCode: $statusCode,
            context: $context,
        );

        $responseData = $this->decodeTokenResponseBody(
            body: $body,
            context: $context,
        );

        try {
            return OidcTokenResponseDto::fromArray(data: $responseData);
        } catch (InvalidArgumentException | RuntimeException | TypeError $exception) {
            throw new KeycloakInvalidResponseException(
                context: $context,
                previous: $exception,
            );
        }
    }

    private function createTokenResponseContext(
        RequestInterface $request,
        ResponseInterface $response,
        string $responseBody,
    ): KeycloakErrorContext {
        $parsedErrorResponse = KeycloakErrorResponseParser::parse($responseBody);

        return new KeycloakErrorContext(
            method: $request->getMethod(),
            uri: SafeUriSanitizer::sanitize((string) $request->getUri()),
            statusCode: $response->getStatusCode(),
            responseBody: $this->sanitizeTokenResponseBody($responseBody),
            keycloakError: $parsedErrorResponse->getError(),
            keycloakErrorDescription: $this->sanitizeDiagnosticText(
                $parsedErrorResponse->getErrorDescription(),
            ),
            correlationId: $this->extractCorrelationId($response),
        );
    }

    /**
     * @return array<mixed>
     */
    private function decodeTokenResponseBody(
        string $body,
        KeycloakErrorContext $context,
    ): array {
        try {
            $data = json_decode(
                json: $body,
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new KeycloakInvalidResponseException(
                context: $context,
                previous: $exception,
            );
        }

        if (!is_array($data)) {
            throw new KeycloakInvalidResponseException($context);
        }

        return $data;
    }

    private function extractCorrelationId(ResponseInterface $response): ?string
    {
        foreach (
            [
                'X-Request-Id',
                'X-Correlation-Id',
                'X-Request-ID',
                'X-Correlation-ID',
            ] as $headerName
        ) {
            $headerValue = $response->getHeaderLine($headerName);

            if ($headerValue !== '') {
                return $headerValue;
            }
        }

        return null;
    }

    private function throwForUnsuccessfulTokenStatusCode(
        int $statusCode,
        KeycloakErrorContext $context,
    ): void {
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        throw match (true) {
            $statusCode === 401 => new KeycloakAuthenticationException($context),
            $statusCode === 403 => new KeycloakAuthorizationException($context),
            $statusCode === 404 => new KeycloakNotFoundException($context),
            $statusCode === 409 => new KeycloakConflictException($context),
            $statusCode === 429 => new KeycloakRateLimitException($context),
            $statusCode >= 500 && $statusCode <= 599 => new KeycloakServerException($context),
            default => new KeycloakTransportException($context),
        };
    }

    private function sanitizeTokenResponseBody(string $body): string
    {
        if ($body === '') {
            return $body;
        }

        try {
            $decoded = json_decode(
                json: $body,
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->sanitizeDiagnosticText($body) ?? '';
        }

        $sanitizedPayload = $this->sanitizeDecodedValue($decoded);

        try {
            return json_encode(
                value: $sanitizedPayload,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->sanitizeDiagnosticText($body) ?? '';
        }
    }

    private function sanitizeDiagnosticText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $patterns = [
            '/Authorization\s*:\s*Bearer\s+\S+/i' => '[redacted credentials]',
            '/\bBearer\s+\S+/i' => '[redacted credentials]',
            '/("?(?:access_token|refresh_token|id_token|client_secret|password|code)"?\s*:\s*)"'
                . '([^"]*)"/i' => '$1"[redacted]"',
            '/\b(access_token|refresh_token|id_token|client_secret|password|code)'
                . '\s*=\s*[^&\s;,]+/i' => '$1=[redacted]',
            '/\b(access_token|refresh_token|id_token|client_secret|password|code)'
                . '\s*:\s*[^&\s;,]+/i' => '$1: [redacted]',
            '/\bAuthorization\b/i' => 'redacted',
            '/\bBearer\b/i' => 'redacted',
            '/\bclient_secret\b/i' => 'redacted',
            '/\brefresh_token\b/i' => 'redacted',
            '/\baccess_token\b/i' => 'redacted',
            '/\bpassword\b/i' => 'redacted',
        ];

        $sanitizedText = $text;

        foreach ($patterns as $pattern => $replacement) {
            $sanitizedText = (string) preg_replace(
                $pattern,
                $replacement,
                $sanitizedText,
            );
        }

        return $sanitizedText;
    }

    private function sanitizeDecodedValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveTokenField($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitizeDecodedValue(
                    value: $itemValue,
                    key: is_string($itemKey) ? $itemKey : null,
                );
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeDiagnosticText($value) ?? '';
        }

        return $value;
    }

    private function isSensitiveTokenField(string $field): bool
    {
        return in_array(
            strtolower(trim($field)),
            [
                'access_token',
                'refresh_token',
                'id_token',
                'client_secret',
                'password',
                'authorization',
                'code',
            ],
            true,
        );
    }
}
