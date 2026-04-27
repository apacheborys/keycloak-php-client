<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;

interface KeycloakUserIdentifierAttributeServiceInterface
{
    public function ensureUserIdentifierAttribute(
        string $realm,
        EnsureUserIdentifierAttributeDto $dto
    ): void;
}
