<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakAuthenticationException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak authentication failed';
}
