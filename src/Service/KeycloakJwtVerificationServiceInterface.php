<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

interface KeycloakJwtVerificationServiceInterface
{
    public function verifyJwt(string $jwt): bool;
}
