<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Ramsey\Uuid\UuidInterface;

interface KeycloakUserLookupServiceInterface
{
    public function findUserById(string $realm, UuidInterface $userId, ?string $email = null): KeycloakUser;
}
