<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

readonly final class CredentialsDto
{
    private string $type;

    private string $credentialsData;

    private string $secretData;

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'credentialsData' => $this->credentialsData,
            'secretData' => $this->secretData,
        ];
    }
}
