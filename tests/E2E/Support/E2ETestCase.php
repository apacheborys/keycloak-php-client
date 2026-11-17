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
 * Tests only ever use the API client's credentials (see E2EStand for the preconfigured and
 * provisioned modes); administrator credentials, if given, are used solely to create/remove the stand.
 *
 * Tests are skipped (not failed) when no KEYCLOAK_E2E_* variable is set, so the suite is always
 * safe to invoke; an incomplete configuration fails. Run it with: composer test:e2e
 */
abstract class E2ETestCase extends TestCase
{
    protected static E2EConfig $config;

    /**
     * Short random marker shared by everything one test class creates, keeping
     * test classes independent from each other inside the shared run realm.
     */
    protected static string $runId;

    public static function setUpBeforeClass(): void
    {
        $problem = E2ESettings::configurationProblem();
        if ($problem !== null && E2ESettings::isUnconfigured()) {
            E2EReport::notConfigured($problem);
            self::markTestSkipped($problem);
        }
        if ($problem !== null) {
            // Some variables are set, so a run was intended: a typo must fail loudly, not skip silently.
            throw new \LogicException($problem);
        }

        static::$config = E2EStand::config();
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
