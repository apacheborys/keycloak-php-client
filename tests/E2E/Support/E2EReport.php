<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

/**
 * Collects the outcome of an E2E run and writes it as one JSON document for scripts and CI.
 *
 * Schema "keycloak-php-client.e2e-report/v1" (see docs/e2e-testing.md):
 *   status   "passed" | "failed" | "skipped" - the single field a script needs to check
 *   problems list of human-readable reasons when status != "passed"
 *   keycloak where the run happened (never contains secrets)
 *   stand    lifecycle of the disposable realm/client (provisioned mode) incl. cleanup result
 *   summary  counts per outcome; tests: one entry per test (or per skipped test class)
 */
final class E2EReport
{
    public const string SCHEMA = 'keycloak-php-client.e2e-report/v1';
    public const string TEST_NAMESPACE = 'Apacheborys\\KeycloakPhpClient\\Tests\\E2E\\';

    private const array OUTCOMES = ['passed', 'failed', 'errored', 'skipped', 'incomplete'];

    private static ?string $startedAt = null;
    private static ?string $notConfigured = null;
    private static ?E2EConfig $config = null;

    /** @var array{provisioned: bool, provisioningError: ?string, cleanup: string, cleanupError: ?string} */
    private static array $stand = [
        'provisioned' => false,
        'provisioningError' => null,
        'cleanup' => 'not_needed',
        'cleanupError' => null,
    ];

    /** @var list<array{id: string, class: string, method: ?string, status: string, tests: int, message: ?string}> */
    private static array $tests = [];

    /** @var list<string> */
    private static array $notes = [];

    public static function isE2EClass(string $className): bool
    {
        return str_starts_with($className, self::TEST_NAMESPACE);
    }

    public static function recordTest(
        string $class,
        ?string $method,
        string $status,
        ?string $message = null,
        int $tests = 1,
    ): void {
        self::$startedAt ??= date(DATE_ATOM);
        self::$tests[] = [
            'id' => $method === null ? $class : $class . '::' . $method,
            'class' => $class,
            'method' => $method,
            'status' => $status,
            'tests' => $tests,
            'message' => $message,
        ];
    }

    public static function notConfigured(string $reason): void
    {
        self::$notConfigured ??= $reason;
    }

    public static function configured(E2EConfig $config): void
    {
        self::$config = $config;
    }

    public static function provisioned(): void
    {
        self::$stand['provisioned'] = true;
    }

    public static function provisioningFailed(\Throwable $exception): void
    {
        self::$stand['provisioningError'] = $exception->getMessage();
    }

    public static function cleanup(string $status, ?string $error = null): void
    {
        self::$stand['cleanup'] = $status;
        self::$stand['cleanupError'] = $error;
    }

    public static function note(string $note): void
    {
        self::$notes[] = $note;
    }

    /**
     * Writes the report if any E2E test took part in this run; returns the path written, or null.
     */
    public static function write(string $path): ?string
    {
        if (self::$tests === []) {
            return null; // e.g. a unit-suite run: nothing to report
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create report directory {$directory}.");
        }

        $json = json_encode(self::build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($path, $json . "\n");

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $summary = array_fill_keys(['total', ...self::OUTCOMES], 0);
        foreach (self::$tests as $test) {
            $summary['total'] += $test['tests'];
            $summary[$test['status']] += $test['tests'];
        }

        $problems = [];
        if (self::$stand['provisioningError'] !== null) {
            $problems[] = 'E2E setup failed: ' . self::$stand['provisioningError'];
        }
        if ($summary['failed'] + $summary['errored'] > 0) {
            $problems[] = sprintf('%d failed, %d errored test(s).', $summary['failed'], $summary['errored']);
        }
        if (self::$stand['cleanup'] === 'failed') {
            $problems[] = 'Stand cleanup failed, manual removal needed: ' . self::$stand['cleanupError'];
        }

        $status = match (true) {
            $problems !== [] => 'failed',
            $summary['skipped'] === $summary['total'] => 'skipped',
            default => 'passed',
        };
        if ($status === 'skipped') {
            $problems[] = self::$notConfigured ?? 'All E2E tests were skipped.';
        }

        $config = self::$config;

        return [
            'schema' => self::SCHEMA,
            'status' => $status,
            'problems' => $problems,
            'startedAt' => self::$startedAt,
            'finishedAt' => date(DATE_ATOM),
            'keycloak' => $config === null ? null : [
                'mode' => $config->mode,
                'baseUrl' => $config->baseUrl,
                'realm' => $config->realm,
                'clientId' => $config->clientId,
                'version' => $config->keycloakVersion,
                'passwordHashingProviders' => $config->passwordHashingProviders,
            ],
            'stand' => self::$stand,
            'notes' => self::$notes,
            'summary' => $summary,
            'tests' => self::$tests,
        ];
    }
}
