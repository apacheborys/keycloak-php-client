<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

/**
 * One disposable Keycloak stand per PHPUnit run.
 *
 * Provisioning is lazy: it happens when the first E2E test class starts, so `composer test`
 * (unit suite) never contacts Keycloak. Teardown is triggered by E2EStandExtension when the
 * test run finishes, with a shutdown-function fallback for abnormal exits.
 */
final class E2EStand
{
    private static ?KeycloakTestStand $stand = null;
    private static ?E2EConfig $config = null;
    private static ?\Throwable $provisioningError = null;

    public static function config(): E2EConfig
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Do not retry a failed provisioning for every test class; report the original error.
        if (self::$provisioningError !== null) {
            throw new \RuntimeException(
                'E2E stand provisioning failed earlier in this run.',
                0,
                self::$provisioningError,
            );
        }

        $stand = new KeycloakTestStand(E2EAdminSettings::fromEnvironment());
        self::$stand = $stand;
        register_shutdown_function([self::class, 'destroy']);

        try {
            self::$config = $stand->provision();
        } catch (\Throwable $exception) {
            self::$provisioningError = $exception;

            throw $exception;
        }

        fwrite(STDERR, sprintf(
            "[e2e] Provisioned realm \"%s\" with client \"%s\".\n",
            self::$config->realm,
            self::$config->clientId,
        ));

        return self::$config;
    }

    public static function destroy(): void
    {
        $stand = self::$stand;
        if ($stand === null) {
            return;
        }

        self::$stand = null;
        $realm = $stand->realm();

        try {
            $stand->destroy();
            if ($realm !== null) {
                fwrite(STDERR, sprintf("[e2e] Removed client and realm \"%s\".\n", $realm));
            }
        } catch (\Throwable $exception) {
            fwrite(STDERR, sprintf(
                "[e2e] Cleanup FAILED for realm \"%s\": %s\nDelete it manually (docs/e2e-testing.md).\n",
                $realm ?? '?',
                $exception->getMessage(),
            ));
        }
    }
}
