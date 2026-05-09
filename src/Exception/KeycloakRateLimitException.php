<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakRateLimitException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak rate limit exceeded';
}
