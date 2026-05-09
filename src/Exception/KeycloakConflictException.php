<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

final class KeycloakConflictException extends KeycloakException
{
    protected const string DEFAULT_SUMMARY = 'Keycloak request conflicted with current state';
}
