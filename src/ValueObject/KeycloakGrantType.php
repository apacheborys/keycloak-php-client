<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

enum KeycloakGrantType: string
{
    case PASSWORD = 'password';
    case REFRESH_TOKEN = 'refresh_token';
}
