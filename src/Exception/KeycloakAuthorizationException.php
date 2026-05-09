<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakAuthorizationException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak authorization failed';
}
