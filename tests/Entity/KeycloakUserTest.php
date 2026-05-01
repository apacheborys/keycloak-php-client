<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Entity;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use PHPUnit\Framework\TestCase;

final class KeycloakUserTest extends TestCase
{
    public function testGetIdReturnsKeycloakUuidObject(): void
    {
        $user = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $user->getId()->toString());
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $user->getKeycloakId());
    }
}
