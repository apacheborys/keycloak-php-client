<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;

/**
 * Connection settings for the optional end-to-end suite, read from environment variables.
 *
 * The suite talks to a real Keycloak instance, so every value must be provided explicitly.
 * See docs/e2e-testing.md for how to prepare the realm and the API client.
 */
final readonly class E2EConfig
{
    public const string ENV_BASE_URL = 'KEYCLOAK_E2E_BASE_URL';
    public const string ENV_REALM = 'KEYCLOAK_E2E_REALM';
    public const string ENV_CLIENT_ID = 'KEYCLOAK_E2E_CLIENT_ID';
    public const string ENV_CLIENT_SECRET = 'KEYCLOAK_E2E_CLIENT_SECRET';

    private const array REQUIRED = [
        self::ENV_BASE_URL,
        self::ENV_REALM,
        self::ENV_CLIENT_ID,
        self::ENV_CLIENT_SECRET,
    ];

    private function __construct(
        public string $baseUrl,
        public string $realm,
        public string $clientId,
        public string $clientSecret,
    ) {
    }

    /**
     * @return list<string> names of required variables that are unset or empty
     */
    public static function missingVariables(): array
    {
        return array_values(
            array_filter(
                self::REQUIRED,
                static fn (string $name): bool => self::read($name) === null,
            ),
        );
    }

    public static function fromEnvironment(): self
    {
        $missing = self::missingVariables();
        if ($missing !== []) {
            throw new \LogicException('Missing E2E configuration: ' . implode(', ', $missing));
        }

        return new self(
            baseUrl: rtrim((string) self::read(self::ENV_BASE_URL), '/'),
            realm: (string) self::read(self::ENV_REALM),
            clientId: (string) self::read(self::ENV_CLIENT_ID),
            clientSecret: (string) self::read(self::ENV_CLIENT_SECRET),
        );
    }

    public function toClientConfig(?string $clientSecret = null): KeycloakClientConfig
    {
        return new KeycloakClientConfig(
            baseUrl: $this->baseUrl,
            clientRealm: $this->realm,
            clientId: $this->clientId,
            clientSecret: $clientSecret ?? $this->clientSecret,
        );
    }

    private static function read(string $name): ?string
    {
        // PHPUnit <env> entries populate $_ENV; real shell variables are visible via getenv().
        $value = $_ENV[$name] ?? getenv($name);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
