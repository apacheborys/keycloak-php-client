<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

enum ClientScopeRealmAssignmentType: string
{
    case NONE = 'none';
    case DEFAULT = 'default';
    case OPTIONAL = 'optional';
}
