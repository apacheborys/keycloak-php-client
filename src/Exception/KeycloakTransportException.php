<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakTransportException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak transport error';
}
