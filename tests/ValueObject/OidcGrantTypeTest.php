<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\ValueObject;

use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;

final class OidcGrantTypeTest extends TestCase
{
    public function testClientCredentialsValueIsExposed(): void
    {
        self::assertSame('client_credentials', OidcGrantType::CLIENT_CREDENTIALS->value);
    }
}
