<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

enum OidcGrantType: string
{
    case CLIENT_CREDENTIALS = 'client_credentials';
    case PASSWORD = 'password';
    case REFRESH_TOKEN = 'refresh_token';
}
