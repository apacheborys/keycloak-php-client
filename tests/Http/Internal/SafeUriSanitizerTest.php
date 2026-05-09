<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\Http\Internal\SafeUriSanitizer;
use PHPUnit\Framework\TestCase;

final class SafeUriSanitizerTest extends TestCase
{
    public function testSanitizerRedactsSensitiveQueryParameters(): void
    {
        $sanitizedUri = SafeUriSanitizer::sanitize(
            'https://keycloak.example:8443/realms/master/protocol/openid-connect/token'
            . '?client_secret=top-secret'
            . '&access_token=access-secret'
            . '&refresh_token=refresh-secret'
            . '&password=SuperSecretPassword%212026'
            . '&code=authorization-code'
        );

        self::assertSame(
            'https://keycloak.example:8443/realms/master/protocol/openid-connect/token'
            . '?client_secret=[redacted]'
            . '&access_token=[redacted]'
            . '&refresh_token=[redacted]'
            . '&password=[redacted]'
            . '&code=[redacted]',
            $sanitizedUri,
        );
    }

    public function testSanitizerKeepsSafeQueryParameters(): void
    {
        $sanitizedUri = SafeUriSanitizer::sanitize(
            'https://keycloak.example/admin/realms/master/users'
            . '?client_id=backend&scope=openid%20email&exact=true',
        );

        self::assertSame(
            'https://keycloak.example/admin/realms/master/users'
            . '?client_id=backend&scope=openid%20email&exact=true',
            $sanitizedUri,
        );
    }

    public function testSanitizerReturnsConservativeValueForMalformedUri(): void
    {
        $sanitizedUri = SafeUriSanitizer::sanitize(
            'https://keycloak.example:99999?access_token=access-secret',
        );

        self::assertSame('[invalid URI]', $sanitizedUri);
    }
}
