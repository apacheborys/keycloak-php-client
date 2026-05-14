<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Token;

use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;

interface AccessTokenProviderInterface
{
    public function getAccessToken(): JsonWebToken;
}
