<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface KeycloakUserIdentifierAttributeServiceInterface
{
    public function ensureUserIdentifierAttribute(
        KeycloakUserInterface $localUser,
        EnsureUserIdentifierAttributeDto $dto
    ): void;
}
