<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

final readonly class ParsedKeycloakErrorResponse
{
    public function __construct(
        private string $body,
        private ?string $error,
        private ?string $errorDescription,
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorDescription(): ?string
    {
        return $this->errorDescription;
    }
}
