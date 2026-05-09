<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use JsonException;
use stdClass;

final class KeycloakErrorResponseParser
{
    public static function parse(string $body): ParsedKeycloakErrorResponse
    {
        if ($body === '') {
            return new ParsedKeycloakErrorResponse(
                body: $body,
                error: null,
                errorDescription: null,
            );
        }

        try {
            $decoded = json_decode($body, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new ParsedKeycloakErrorResponse(
                body: $body,
                error: null,
                errorDescription: null,
            );
        }

        if (!$decoded instanceof stdClass) {
            return new ParsedKeycloakErrorResponse(
                body: $body,
                error: null,
                errorDescription: null,
            );
        }

        $error = isset($decoded->error) && is_string($decoded->error)
            ? $decoded->error
            : null;

        $errorDescription = isset($decoded->error_description)
            && is_string($decoded->error_description)
            ? $decoded->error_description
            : null;

        if (
            $errorDescription === null
            && isset($decoded->errorMessage)
            && is_string($decoded->errorMessage)
        ) {
            $errorDescription = $decoded->errorMessage;
        }

        return new ParsedKeycloakErrorResponse(
            body: $body,
            error: $error,
            errorDescription: $errorDescription,
        );
    }
}
