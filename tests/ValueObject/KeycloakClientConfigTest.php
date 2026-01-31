<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\ValueObject;

use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class KeycloakClientConfigTest extends TestCase
{
    public function testGetters(): void
    {
        $config = new KeycloakClientConfig(
            baseUrl: 'http://localhost:8080',
            clientRealm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            realmListTtl: 120,
        );

        self::assertSame('http://localhost:8080', $config->getBaseUrl());
        self::assertSame('master', $config->getClientRealm());
        self::assertSame('backend', $config->getClientId());
        self::assertSame('secret', $config->getClientSecret());
        self::assertSame(120, $config->getRealmListTtl());
    }

    public function testInvalidUrlThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KeycloakClientConfig(
            baseUrl: 'not-a-url',
            clientRealm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
        );
    }
}
