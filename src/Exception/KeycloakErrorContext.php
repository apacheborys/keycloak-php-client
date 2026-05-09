<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

use Apacheborys\KeycloakPhpClient\Http\Internal\SafeUriSanitizer;

final readonly class KeycloakErrorContext
{
    private string $method;
    private string $uri;

    public function __construct(
        string $method,
        string $uri,
        private ?int $statusCode = null,
        private ?string $responseBody = null,
        private ?string $keycloakError = null,
        private ?string $keycloakErrorDescription = null,
        private ?string $correlationId = null,
    ) {
        $normalizedMethod = strtoupper(trim($method));

        $this->method = $normalizedMethod !== '' ? $normalizedMethod : 'UNKNOWN';
        $this->uri = SafeUriSanitizer::sanitize($uri);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getKeycloakError(): ?string
    {
        return $this->keycloakError;
    }

    public function getKeycloakErrorDescription(): ?string
    {
        return $this->keycloakErrorDescription;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }
}
