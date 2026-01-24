<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;

final readonly class ResetUserPasswordDto
{
    public function __construct(
        private string $realm,
        private KeycloakUser $user,
        private KeycloakCredentialType $type,
        private string $value,
        private bool $temporary = true,
    ) {
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getUser(): KeycloakUser
    {
        return $this->user;
    }

    public function getType(): KeycloakCredentialType
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }
}
