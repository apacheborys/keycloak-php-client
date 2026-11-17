<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18Client;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;

/**
 * Resolves the Keycloak target for one PHPUnit run.
 *
 * - preconfigured mode: uses the manually prepared realm/client as-is; nothing to create or delete.
 * - provisioned mode: creates a disposable realm + client on first use (lazy, so `composer test`
 *   never contacts Keycloak) and deletes them when the run finishes (E2EStandExtension), with a
 *   shutdown-function fallback for abnormal exits.
 *
 * Everything talks to Keycloak over HTTP only, so it works the same for local, staging and sandbox servers.
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

        $settings = E2ESettings::fromEnvironment();

        try {
            self::$config = $settings->mode === E2ESettings::MODE_PRECONFIGURED
                ? self::preconfigured($settings)
                : self::provision($settings);
        } catch (\Throwable $exception) {
            self::$provisioningError = $exception;
            E2EReport::provisioningFailed($exception);

            throw $exception;
        }

        E2EReport::configured(self::$config);

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
        if ($realm === null) {
            return; // provisioning never got as far as creating the realm
        }

        try {
            $stand->destroy();
            E2EReport::cleanup('succeeded');
            fwrite(STDERR, sprintf("[e2e] Removed client and realm \"%s\".\n", $realm));
        } catch (\Throwable $exception) {
            E2EReport::cleanup('failed', $exception->getMessage());
            fwrite(STDERR, sprintf(
                "[e2e] Cleanup FAILED for realm \"%s\": %s\nDelete it manually (docs/e2e-testing.md).\n",
                $realm,
                $exception->getMessage(),
            ));
        }
    }

    private static function provision(E2ESettings $settings): E2EConfig
    {
        $stand = new KeycloakTestStand($settings);
        self::$stand = $stand;
        register_shutdown_function([self::class, 'destroy']);

        $config = $stand->provision();
        E2EReport::provisioned();
        E2EReport::cleanup('pending');
        fwrite(STDERR, sprintf(
            "[e2e] Provisioned realm \"%s\" with client \"%s\".\n",
            $config->realm,
            $config->clientId,
        ));

        return $config;
    }

    private static function preconfigured(E2ESettings $settings): E2EConfig
    {
        $config = new E2EConfig(
            mode: E2ESettings::MODE_PRECONFIGURED,
            baseUrl: $settings->baseUrl,
            realm: $settings->realm,
            clientId: $settings->clientId,
            clientSecret: $settings->clientSecret,
            passwordHashingProviders: [],
        );

        if ($settings->passwordHashingProviders !== null) {
            return self::withServerInfo($config, $settings->passwordHashingProviders, null);
        }

        // Best effort with the API client's own token; never with administrator credentials.
        try {
            $token = new KeycloakHttpClientFactory()->createClientCredentialsTokenProvider(
                config: $config->toClientConfig(),
                httpClient: new NativePsr18Client(),
                requestFactory: new SimpleRequestFactory(),
                streamFactory: new SimpleStreamFactory(),
            )->getAccessToken()->getRawToken();

            $response = new NativePsr18Client()->sendRequest(
                new SimpleRequestFactory()->createRequest('GET', $config->baseUrl . '/admin/serverinfo')
                    ->withHeader('Authorization', 'Bearer ' . $token)
                    ->withHeader('Accept', 'application/json'),
            );
            $decoded = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() === 200 && is_array($decoded)) {
                $info = KeycloakTestStand::parseServerInfo($decoded);

                return self::withServerInfo($config, $info['hashing'], $info['version']);
            }

            E2EReport::note(sprintf(
                'Server info not readable by the API client (HTTP %d); set %s to enable hash-import tests.',
                $response->getStatusCode(),
                E2ESettings::ENV_PASSWORD_HASHING_PROVIDERS,
            ));
        } catch (\Throwable $exception) {
            // Connectivity problems surface in ConnectivityE2ETest with a precise error; do not fail here.
            E2EReport::note('Server info lookup failed: ' . $exception->getMessage());
        }

        return $config;
    }

    /**
     * @param list<string> $hashing
     */
    private static function withServerInfo(E2EConfig $config, array $hashing, ?string $version): E2EConfig
    {
        return new E2EConfig(
            mode: $config->mode,
            baseUrl: $config->baseUrl,
            realm: $config->realm,
            clientId: $config->clientId,
            clientSecret: $config->clientSecret,
            passwordHashingProviders: $hashing,
            keycloakVersion: $version,
        );
    }
}
