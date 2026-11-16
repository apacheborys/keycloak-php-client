<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;

/**
 * Connection settings for the opt-in E2E suite, read from KEYCLOAK_E2E_* environment variables.
 */
final readonly class E2EEnvironment
{
    public const string BASE_URL = 'KEYCLOAK_E2E_BASE_URL';
    public const string REALM = 'KEYCLOAK_E2E_REALM';
    public const string CLIENT_ID = 'KEYCLOAK_E2E_CLIENT_ID';
    public const string CLIENT_SECRET = 'KEYCLOAK_E2E_CLIENT_SECRET';

    private const array REQUIRED = [self::BASE_URL, self::REALM, self::CLIENT_ID, self::CLIENT_SECRET];

    private function __construct(
        public string $baseUrl,
        public string $realm,
        public string $clientId,
        public string $clientSecret,
    ) {
    }

    /**
     * Returns null when at least one required variable is missing.
     */
    public static function fromEnvironment(): ?self
    {
        if (self::missingVariables() !== []) {
            return null;
        }

        return new self(
            baseUrl: rtrim(self::read(self::BASE_URL), '/'),
            realm: self::read(self::REALM),
            clientId: self::read(self::CLIENT_ID),
            clientSecret: self::read(self::CLIENT_SECRET),
        );
    }

    /**
     * @return list<string>
     */
    public static function missingVariables(): array
    {
        return array_values(array_filter(self::REQUIRED, static fn (string $name): bool => self::read($name) === ''));
    }

    public function clientConfig(): KeycloakClientConfig
    {
        return new KeycloakClientConfig(
            baseUrl: $this->baseUrl,
            clientRealm: $this->realm,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
        );
    }

    public function issuer(): string
    {
        return $this->baseUrl . '/realms/' . $this->realm;
    }

    private static function read(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
