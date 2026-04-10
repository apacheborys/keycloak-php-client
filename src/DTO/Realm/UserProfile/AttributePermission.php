<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile;

enum AttributePermission: string
{
    case USER = 'user';
    case ADMIN = 'admin';
}
