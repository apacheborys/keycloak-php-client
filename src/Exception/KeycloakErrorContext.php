<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final readonly class KeycloakErrorContext
{
    /**
     * @var list<string>
     */
    private const array SENSITIVE_QUERY_PARAMETERS = [
        'access_token',
        'authorization',
        'client_secret',
        'password',
        'refresh_token',
    ];

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
        $this->uri = self::sanitizeUri($uri);
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

    private static function sanitizeUri(string $uri): string
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            return self::sanitizeMalformedUri($uri);
        }

        $sanitizedUri = '';

        if (isset($parts['scheme'], $parts['host'])) {
            $sanitizedUri = $parts['scheme'] . '://' . $parts['host'];

            if (isset($parts['port'])) {
                $sanitizedUri .= ':' . $parts['port'];
            }
        }

        $sanitizedUri .= (string) ($parts['path'] ?? '');

        $sanitizedQuery = self::sanitizeQueryString(
            isset($parts['query']) ? (string) $parts['query'] : null,
        );

        if ($sanitizedQuery !== null && $sanitizedQuery !== '') {
            $sanitizedUri .= '?' . $sanitizedQuery;
        }

        return $sanitizedUri;
    }

    private static function sanitizeMalformedUri(string $uri): string
    {
        $queryPosition = strpos($uri, '?');

        if ($queryPosition === false) {
            return $uri;
        }

        $path = substr($uri, 0, $queryPosition);
        $query = substr($uri, $queryPosition + 1);
        $sanitizedQuery = self::sanitizeQueryString($query);

        if ($sanitizedQuery === null || $sanitizedQuery === '') {
            return $path;
        }

        return $path . '?' . $sanitizedQuery;
    }

    private static function sanitizeQueryString(?string $query): ?string
    {
        if ($query === null || $query === '') {
            return $query;
        }

        parse_str($query, $parameters);

        if ($parameters === []) {
            return null;
        }

        $sanitizedParameters = self::sanitizeQueryParameters($parameters);

        if ($sanitizedParameters === []) {
            return null;
        }

        return http_build_query(
            data: $sanitizedParameters,
            arg_separator: '&',
            encoding_type: PHP_QUERY_RFC3986,
        );
    }

    /**
     * @param array<mixed> $parameters
     *
     * @return array<mixed>
     */
    private static function sanitizeQueryParameters(array $parameters): array
    {
        $sanitizedParameters = [];

        foreach ($parameters as $key => $value) {
            if (is_string($key) && self::isSensitiveQueryParameter($key)) {
                continue;
            }

            if (is_array($value)) {
                $sanitizedNestedValue = self::sanitizeQueryParameters($value);

                if ($sanitizedNestedValue === []) {
                    continue;
                }

                $sanitizedParameters[$key] = $sanitizedNestedValue;

                continue;
            }

            $sanitizedParameters[$key] = $value;
        }

        return $sanitizedParameters;
    }

    private static function isSensitiveQueryParameter(string $parameterName): bool
    {
        return in_array(
            strtolower($parameterName),
            self::SENSITIVE_QUERY_PARAMETERS,
            true,
        );
    }
}
