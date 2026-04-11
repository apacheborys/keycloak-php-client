<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

enum AttributePermission: string
{
    case USER = 'user';
    case ADMIN = 'admin';
}
