<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthorizationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakConflictException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakErrorContext;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakInvalidResponseException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakRateLimitException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakServerException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakTransportException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class KeycloakHttpCore
{
    private const string CLIENT_NAME = 'Keycloak PHP Client';

    public function __construct(
        private string $baseUrl,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest(request: $request);
        } catch (ClientExceptionInterface $exception) {
            throw new KeycloakTransportException(
                context: $this->createRequestContext($request),
                previous: $exception,
            );
        }
    }

    public function buildEndpoint(string $path, string $query = ''): string
    {
        $endpoint = rtrim(string: $this->baseUrl, characters: '/') . '/' . ltrim($path, '/');

        if ($query === '') {
            return $endpoint;
        }

        return $endpoint . '?' . $query;
    }

    /**
     * @param array<string, string> $headers
     */
    public function createRequest(
        string $method,
        string $endpoint,
        array $headers = [],
        ?string $body = null,
    ): RequestInterface {
        $request = $this->requestFactory
            ->createRequest($method, $endpoint)
            ->withHeader('User-Agent', self::CLIENT_NAME);

        foreach ($headers as $headerName => $headerValue) {
            $request = $request->withHeader($headerName, $headerValue);
        }

        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    public function assertSuccessfulResponse(
        RequestInterface $request,
        ResponseInterface $response,
    ): void {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = $this->readResponseBody($response);
        $context = $this->createResponseContext(
            request: $request,
            response: $response,
            responseBody: $body,
        );

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

    /**
     * @param list<int> $allowedStatusCodes
     */
    public function assertSuccessfulResponseOrAllowedStatus(
        RequestInterface $request,
        ResponseInterface $response,
        array $allowedStatusCodes,
    ): void {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        if (in_array($statusCode, $allowedStatusCodes, true)) {
            return;
        }

        $this->assertSuccessfulResponse($request, $response);
    }

    /**
     * @return array<mixed>
     */
    public function decodeJsonResponse(
        RequestInterface $request,
        ResponseInterface $response,
    ): array {
        $body = $this->readResponseBody($response);
        $this->assertSuccessfulResponse($request, $response);

        return $this->decodeJsonBody(
            body: $body,
            context: $this->createResponseContext(
                request: $request,
                response: $response,
                responseBody: $body,
            ),
        );
    }

    /**
     * @return array<mixed>
     */
    public function decodeJson(string $body): array
    {
        return $this->decodeJsonBody(
            body: $body,
            context: new KeycloakErrorContext(
                method: 'UNKNOWN',
                uri: 'unknown',
                responseBody: $body,
            ),
        );
    }

    private function createRequestContext(RequestInterface $request): KeycloakErrorContext
    {
        return new KeycloakErrorContext(
            method: $request->getMethod(),
            uri: SafeUriSanitizer::sanitize((string) $request->getUri()),
        );
    }

    private function createResponseContext(
        RequestInterface $request,
        ResponseInterface $response,
        string $responseBody,
    ): KeycloakErrorContext {
        $parsedErrorResponse = KeycloakErrorResponseParser::parse($responseBody);

        return new KeycloakErrorContext(
            method: $request->getMethod(),
            uri: SafeUriSanitizer::sanitize((string) $request->getUri()),
            statusCode: $response->getStatusCode(),
            responseBody: $parsedErrorResponse->getBody(),
            keycloakError: $parsedErrorResponse->getError(),
            keycloakErrorDescription: $parsedErrorResponse->getErrorDescription(),
            correlationId: $this->extractCorrelationId($response),
        );
    }

    /**
     * @return array<mixed>
     */
    private function decodeJsonBody(
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

    private function readResponseBody(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }
}
