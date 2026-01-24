<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

enum HashAlgorithm: string
{
    case BCRYPT = 'bcrypt';

    case ARGON = 'argon';

    case MD5 = 'md5';
}
