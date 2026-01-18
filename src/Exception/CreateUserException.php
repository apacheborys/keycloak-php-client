<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Exception;

use LogicException;
use Throwable;

class CreateUserException extends LogicException
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        return parent::__construct(
            message: "During user creation error happen: $message",
            code: $code,
            previous: $previous
        );
    }
}
