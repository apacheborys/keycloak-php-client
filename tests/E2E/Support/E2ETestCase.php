<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceFactory;
use Apacheborys\KeycloakPhpClient\Service\KeycloakServiceInterface;
use Apacheborys\KeycloakPhpClient\Tests\Support\Cache\InMemoryCachePool;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18Client;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use Apacheborys\KeycloakPhpClient\Token\AccessTokenProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that run against a real Keycloak instance.
 *
 * Tests are skipped (not failed) when the KEYCLOAK_E2E_* variables are not configured,
 * so the suite is always safe to invoke. Run it with: composer test:e2e
 */
abstract class E2ETestCase extends TestCase
{
    protected static E2EConfig $config;

    /**
     * Short random marker shared by everything one test class creates in Keycloak,
     * so leftovers from an interrupted run are easy to identify and remove.
     */
    protected static string $runId;

    public static function setUpBeforeClass(): void
    {
        $missing = E2EConfig::missingVariables();
        if ($missing !== []) {
            self::markTestSkipped(
                'Real Keycloak E2E tests are not configured. Missing: ' . implode(', ', $missing)
                . '. See docs/e2e-testing.md.',
            );
        }

        static::$config = E2EConfig::fromEnvironment();
        static::$runId = bin2hex(random_bytes(4));
    }

    protected static function createHttpClient(?string $clientSecret = null): KeycloakHttpClientInterface
    {
        return new KeycloakHttpClientFactory()->create(
            config: static::$config->toClientConfig(clientSecret: $clientSecret),
            httpClient: new NativePsr18Client(),
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
            cache: new InMemoryCachePool(),
        );
    }

    protected static function createTokenProvider(?string $clientSecret = null): AccessTokenProviderInterface
    {
        return new KeycloakHttpClientFactory()->createClientCredentialsTokenProvider(
            config: static::$config->toClientConfig(clientSecret: $clientSecret),
            httpClient: new NativePsr18Client(),
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
        );
    }

    /**
     * @param list<LocalKeycloakUserBridgeMapperInterface> $mappers
     */
    protected static function createService(
        KeycloakHttpClientInterface $httpClient,
        array $mappers,
    ): KeycloakServiceInterface {
        return new KeycloakServiceFactory()->create(httpClient: $httpClient, mappers: $mappers);
    }

    protected static function resourceName(string $suffix): string
    {
        return 'e2e-' . static::$runId . '-' . $suffix;
    }

    /**
     * Runs a cleanup step without letting its failure hide the original test result.
     */
    protected static function cleanup(callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $exception) {
            fwrite(STDERR, sprintf("[e2e cleanup] %s: %s\n", $exception::class, $exception->getMessage()));
        }
    }
}
