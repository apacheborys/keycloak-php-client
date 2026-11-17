<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

/**
 * Administrator access used ONLY to prepare and remove the disposable E2E stand
 * (realm + API client). Functional tests never see these credentials.
 */
final readonly class E2EAdminSettings
{
    public const string ENV_BASE_URL = 'KEYCLOAK_E2E_BASE_URL';
    public const string ENV_ADMIN_USERNAME = 'KEYCLOAK_E2E_ADMIN_USERNAME';
    public const string ENV_ADMIN_PASSWORD = 'KEYCLOAK_E2E_ADMIN_PASSWORD';
    public const string ENV_ADMIN_REALM = 'KEYCLOAK_E2E_ADMIN_REALM';
    public const string ENV_ADMIN_CLIENT_ID = 'KEYCLOAK_E2E_ADMIN_CLIENT_ID';

    private const array REQUIRED = [
        self::ENV_BASE_URL,
        self::ENV_ADMIN_USERNAME,
        self::ENV_ADMIN_PASSWORD,
    ];

    private function __construct(
        public string $baseUrl,
        public string $username,
        public string $password,
        public string $realm,
        public string $clientId,
    ) {
    }

    /**
     * @return list<string> names of required variables that are unset or empty
     */
    public static function missingVariables(): array
    {
        return array_values(
            array_filter(self::REQUIRED, static fn (string $name): bool => self::read($name) === null),
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
            username: (string) self::read(self::ENV_ADMIN_USERNAME),
            password: (string) self::read(self::ENV_ADMIN_PASSWORD),
            realm: self::read(self::ENV_ADMIN_REALM) ?? 'master',
            clientId: self::read(self::ENV_ADMIN_CLIENT_ID) ?? 'admin-cli',
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
