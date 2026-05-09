<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

final class SafeUriSanitizer
{
    private const string INVALID_URI = '[invalid URI]';
    private const string REDACTED_VALUE = '[redacted]';

    /**
     * @var list<string>
     */
    private const array SENSITIVE_QUERY_PARAMETERS = [
        'access_token',
        'assertion',
        'client_secret',
        'code',
        'id_token',
        'password',
        'refresh_token',
        'secret',
        'token',
    ];

    public static function sanitize(string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        $parts = parse_url($uri);

        if ($parts === false) {
            return self::INVALID_URI;
        }

        $sanitizedUri = self::buildBaseUri($parts);
        $sanitizedQuery = self::sanitizeQueryString(
            isset($parts['query']) && is_string($parts['query'])
                ? $parts['query']
                : null,
        );

        if ($sanitizedQuery !== null && $sanitizedQuery !== '') {
            $sanitizedUri .= '?' . $sanitizedQuery;
        }

        if ($sanitizedUri === '') {
            return self::INVALID_URI;
        }

        return $sanitizedUri;
    }

    /**
     * @param array<string, int|string> $parts
     */
    private static function buildBaseUri(array $parts): string
    {
        $sanitizedUri = '';

        if (isset($parts['scheme']) && is_string($parts['scheme'])) {
            $sanitizedUri .= $parts['scheme'] . ':';

            if (isset($parts['host']) && is_string($parts['host'])) {
                $sanitizedUri .= '//';
            }
        }

        if (isset($parts['host']) && is_string($parts['host'])) {
            $sanitizedUri .= $parts['host'];
        }

        if (isset($parts['port']) && is_int($parts['port'])) {
            $sanitizedUri .= ':' . $parts['port'];
        }

        if (isset($parts['path']) && is_string($parts['path'])) {
            $sanitizedUri .= $parts['path'];
        }

        return $sanitizedUri;
    }

    private static function sanitizeQueryString(?string $query): ?string
    {
        if ($query === null || $query === '') {
            return $query;
        }

        $segments = explode('&', $query);
        $sanitizedSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $sanitizedSegments[] = self::sanitizeQuerySegment($segment);
        }

        if ($sanitizedSegments === []) {
            return null;
        }

        return implode('&', $sanitizedSegments);
    }

    private static function sanitizeQuerySegment(string $segment): string
    {
        $separatorPosition = strpos($segment, '=');

        if ($separatorPosition === false) {
            $rawKey = $segment;

            if (self::isSensitiveQueryParameter($rawKey)) {
                return $rawKey . '=' . self::REDACTED_VALUE;
            }

            return $rawKey;
        }

        $rawKey = substr($segment, 0, $separatorPosition);
        $rawValue = substr($segment, $separatorPosition + 1);

        if (self::isSensitiveQueryParameter($rawKey)) {
            return $rawKey . '=' . self::REDACTED_VALUE;
        }

        return $rawKey . '=' . $rawValue;
    }

    private static function isSensitiveQueryParameter(string $rawKey): bool
    {
        $decodedKey = urldecode($rawKey);
        $candidates = [self::normalizeKeySegment($decodedKey)];

        if (preg_match_all('/\[([^\]]*)\]/', $decodedKey, $matches) === false) {
            return false;
        }

        foreach ($matches[1] as $segment) {
            if (!is_string($segment)) {
                continue;
            }

            $candidates[] = self::normalizeKeySegment($segment);
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (in_array($candidate, self::SENSITIVE_QUERY_PARAMETERS, true)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeKeySegment(string $key): string
    {
        $normalizedKey = strtolower(trim($key));

        if (str_ends_with($normalizedKey, '[]')) {
            return substr($normalizedKey, 0, -2);
        }

        return $normalizedKey;
    }
}
