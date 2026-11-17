<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

/**
 * E2E configuration read from environment variables. Two mutually exclusive modes:
 *
 * - preconfigured: a realm and API client were prepared manually (docs/e2e-testing.md); only the
 *   client's credentials are needed. Use this for staging/sandbox environments.
 * - provisioned: administrator credentials are given; a disposable realm + API client is created
 *   over the Admin REST API for the run and deleted afterwards.
 *
 * If client variables are present, preconfigured mode wins and administrator variables are ignored.
 */
final readonly class E2ESettings
{
    public const string MODE_PRECONFIGURED = 'preconfigured';
    public const string MODE_PROVISIONED = 'provisioned';

    public const string ENV_BASE_URL = 'KEYCLOAK_E2E_BASE_URL';

    public const string ENV_REALM = 'KEYCLOAK_E2E_REALM';
    public const string ENV_CLIENT_ID = 'KEYCLOAK_E2E_CLIENT_ID';
    public const string ENV_CLIENT_SECRET = 'KEYCLOAK_E2E_CLIENT_SECRET';

    public const string ENV_ADMIN_USERNAME = 'KEYCLOAK_E2E_ADMIN_USERNAME';
    public const string ENV_ADMIN_PASSWORD = 'KEYCLOAK_E2E_ADMIN_PASSWORD';
    public const string ENV_ADMIN_REALM = 'KEYCLOAK_E2E_ADMIN_REALM';
    public const string ENV_ADMIN_CLIENT_ID = 'KEYCLOAK_E2E_ADMIN_CLIENT_ID';

    /** Optional comma-separated list, e.g. "bcrypt,pbkdf2-sha512"; overrides server detection. */
    public const string ENV_PASSWORD_HASHING_PROVIDERS = 'KEYCLOAK_E2E_PASSWORD_HASHING_PROVIDERS';

    /** Optional path of the machine-readable JSON report (default: build/e2e/report.json). */
    public const string ENV_REPORT = 'KEYCLOAK_E2E_REPORT';

    private const array PRECONFIGURED_VARIABLES = [self::ENV_REALM, self::ENV_CLIENT_ID, self::ENV_CLIENT_SECRET];
    private const array PROVISIONED_VARIABLES = [self::ENV_ADMIN_USERNAME, self::ENV_ADMIN_PASSWORD];

    private function __construct(
        public string $mode,
        public string $baseUrl,
        public string $realm,
        public string $clientId,
        public string $clientSecret,
        public string $adminUsername,
        public string $adminPassword,
        public string $adminRealm,
        public string $adminClientId,
        /** @var list<string>|null null = detect from the server */
        public ?array $passwordHashingProviders,
    ) {
    }

    /**
     * Human-readable reason why the suite cannot run, or null when the configuration is complete.
     */
    public static function configurationProblem(): ?string
    {
        $mode = self::detectMode();
        if ($mode === null) {
            return sprintf(
                'Real Keycloak E2E tests are not configured. Set %s plus either %s (preconfigured realm and client) '
                . 'or %s (disposable realm per run). See docs/e2e-testing.md.',
                self::ENV_BASE_URL,
                implode(', ', self::PRECONFIGURED_VARIABLES),
                implode(', ', self::PROVISIONED_VARIABLES),
            );
        }

        $required = $mode === self::MODE_PRECONFIGURED ? self::PRECONFIGURED_VARIABLES : self::PROVISIONED_VARIABLES;
        $missing = array_values(array_filter(
            [self::ENV_BASE_URL, ...$required],
            static fn (string $name): bool => self::read($name) === null,
        ));

        if ($missing === []) {
            return null;
        }

        return sprintf(
            'Incomplete E2E configuration for %s mode. Missing: %s. See docs/e2e-testing.md.',
            $mode,
            implode(', ', $missing),
        );
    }

    /**
     * True when nothing E2E-specific is set at all (the suite should skip, not fail).
     */
    public static function isUnconfigured(): bool
    {
        return self::detectMode() === null;
    }

    public static function fromEnvironment(): self
    {
        $problem = self::configurationProblem();
        if ($problem !== null) {
            throw new \LogicException($problem);
        }

        $hashing = self::read(self::ENV_PASSWORD_HASHING_PROVIDERS);

        return new self(
            mode: (string) self::detectMode(),
            baseUrl: rtrim((string) self::read(self::ENV_BASE_URL), '/'),
            realm: self::read(self::ENV_REALM) ?? '',
            clientId: self::read(self::ENV_CLIENT_ID) ?? '',
            clientSecret: self::read(self::ENV_CLIENT_SECRET) ?? '',
            adminUsername: self::read(self::ENV_ADMIN_USERNAME) ?? '',
            adminPassword: self::read(self::ENV_ADMIN_PASSWORD) ?? '',
            adminRealm: self::read(self::ENV_ADMIN_REALM) ?? 'master',
            adminClientId: self::read(self::ENV_ADMIN_CLIENT_ID) ?? 'admin-cli',
            passwordHashingProviders: $hashing === null
                ? null
                : array_values(array_filter(
                    array_map('trim', explode(',', $hashing)),
                    static fn (string $provider): bool => $provider !== '',
                )),
        );
    }

    public static function reportPath(): string
    {
        return self::read(self::ENV_REPORT) ?? dirname(__DIR__, 3) . '/build/e2e/report.json';
    }

    private static function detectMode(): ?string
    {
        foreach (self::PRECONFIGURED_VARIABLES as $name) {
            if (self::read($name) !== null) {
                return self::MODE_PRECONFIGURED;
            }
        }

        foreach (self::PROVISIONED_VARIABLES as $name) {
            if (self::read($name) !== null) {
                return self::MODE_PROVISIONED;
            }
        }

        return null;
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
