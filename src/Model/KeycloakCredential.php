<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Model;

use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use JsonSerializable;

final readonly class KeycloakCredential implements JsonSerializable
{
    public function __construct(
        private KeycloakCredentialType $type,
        private string $credentialData,
        private string $secretData,
        private bool $temporary = false,
    ) {
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value(),
            'temporary' => $this->temporary,
            'credentialData' => $this->credentialData,
            'secretData' => $this->secretData,
        ];
    }
}
