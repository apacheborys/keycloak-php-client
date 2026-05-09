<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakNotFoundException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak resource was not found';
}
