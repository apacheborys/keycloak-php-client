<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakInvalidResponseException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak returned an invalid response';
}
