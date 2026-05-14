<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

use RuntimeException;
use Throwable;

class KeycloakException extends RuntimeException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak request failed';

    public function __construct(
        private readonly KeycloakErrorContext $context,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: self::buildMessage(static::DEFAULT_SUMMARY, $context),
            code: $context->getStatusCode() ?? 0,
            previous: $previous,
        );
    }

    public function getContext(): KeycloakErrorContext
    {
        return $this->context;
    }

    private static function buildMessage(
        string $summary,
        KeycloakErrorContext $context,
    ): string {
        $message = sprintf(
            '%s for %s %s with status %s',
            $summary,
            $context->getMethod(),
            $context->getUri() !== '' ? $context->getUri() : '[unknown URI]',
            $context->getStatusCode() !== null
                ? (string) $context->getStatusCode()
                : 'unavailable',
        );

        $keycloakError = self::sanitizeMessageText($context->getKeycloakError());

        if ($keycloakError !== null && $keycloakError !== '') {
            $message .= '; error: ' . $keycloakError;
        }

        $keycloakErrorDescription = self::sanitizeMessageText(
            $context->getKeycloakErrorDescription(),
        );

        if ($keycloakErrorDescription !== null && $keycloakErrorDescription !== '') {
            $message .= '; description: ' . $keycloakErrorDescription;
        }

        $correlationId = self::sanitizeMessageText($context->getCorrelationId());

        if ($correlationId !== null && $correlationId !== '') {
            $message .= '; correlation_id: ' . $correlationId;
        }

        return $message;
    }

    private static function sanitizeMessageText(?string $text): ?string
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
}
