<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service\Internal;

use Ramsey\Uuid\UuidInterface;

final readonly class LocalUserIdentifier
{
    public static function normalize(int|string|UuidInterface $id): string
    {
        if ($id instanceof UuidInterface) {
            return $id->toString();
        }

        return (string) $id;
    }

    public static function logValue(int|string|UuidInterface $id): int|string
    {
        if ($id instanceof UuidInterface) {
            return $id->toString();
        }

        return $id;
    }
}
