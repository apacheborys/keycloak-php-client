<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\Http\Internal\AccessTokenProvider;
use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakHttpCore;
use Apacheborys\KeycloakPhpClient\Http\Internal\OidcInteractionHttpClient;
use Apacheborys\KeycloakPhpClient\Tests\Support\Cache\InMemoryCachePool;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18Client;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\MockServer\PhpMockServer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OidcInteractionHttpClientCacheTest extends TestCase
{
    private ?PhpMockServer $server = null;
    private OidcInteractionHttpClient $client;

    protected function setUp(): void
    {
        try {
            $this->server = new PhpMockServer();
        } catch (RuntimeException $exception) {
            $this->markTestSkipped(
                'Local HTTP server is not available in this environment: ' . $exception->getMessage()
            );
        }

        $cache = new InMemoryCachePool();
        $core = new KeycloakHttpCore(
            baseUrl: $this->server->getBaseUrl(),
            httpClient: new NativePsr18Client(),
            requestFactory: new SimpleRequestFactory(),
            streamFactory: new SimpleStreamFactory(),
            cache: $cache,
        );

        $this->client = new OidcInteractionHttpClient(
            httpCore: $core,
            accessTokenProvider: new AccessTokenProvider(
                httpCore: $core,
                clientRealm: 'master',
                clientId: 'backend',
                clientSecret: 'secret',
            ),
            clientId: 'backend',
            realmListTtl: 3600,
            openIdConfigurationTtl: 86400,
            jwkByKidTtl: 86400,
        );
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    public function testOpenIdConfigurationCacheHitAfterMiss(): void
    {
        $this->server->setScenario(
            [
                'GET /realms/master/.well-known/openid-configuration' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'issuer' => 'http://issuer.example/realms/master',
                                'jwks_uri' => 'http://issuer.example/realms/master/certs-v1',
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $first = $this->client->getOpenIdConfiguration('master', true);
        $second = $this->client->getOpenIdConfiguration('master', true);

        self::assertSame('http://issuer.example/realms/master/certs-v1', $first->getJwksUri());
        self::assertSame($first->getJwksUri(), $second->getJwksUri());
        self::assertCount(1, $this->server->getRequests());
    }

    public function testOpenIdConfigurationRefreshBypassesCache(): void
    {
        $this->server->setScenario(
            [
                'GET /realms/master/.well-known/openid-configuration' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'issuer' => 'http://issuer.example/realms/master',
                                'jwks_uri' => 'http://issuer.example/realms/master/certs-v1',
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'issuer' => 'http://issuer.example/realms/master',
                                'jwks_uri' => 'http://issuer.example/realms/master/certs-v2',
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $cached = $this->client->getOpenIdConfiguration('master', true);
        $refreshed = $this->client->getOpenIdConfiguration('master', false);

        self::assertSame('http://issuer.example/realms/master/certs-v1', $cached->getJwksUri());
        self::assertSame('http://issuer.example/realms/master/certs-v2', $refreshed->getJwksUri());
        self::assertCount(2, $this->server->getRequests());
    }

    public function testGetJwkSupportsCacheMissHitAndRefresh(): void
    {
        $this->server->setScenario(
            [
                'GET /realms/master/protocol/openid-connect/certs' => [
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'keys' => [
                                    [
                                        'kid' => 'kid-1',
                                        'kty' => 'RSA',
                                        'use' => 'sig',
                                        'alg' => 'RS256',
                                        'n' => 'first-modulus',
                                        'e' => 'AQAB',
                                        'x5c' => [],
                                    ],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                    [
                        'status' => 200,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(
                            [
                                'keys' => [
                                    [
                                        'kid' => 'kid-1',
                                        'kty' => 'RSA',
                                        'use' => 'sig',
                                        'alg' => 'RS256',
                                        'n' => 'second-modulus',
                                        'e' => 'AQAB',
                                        'x5c' => [],
                                    ],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ],
                ],
            ],
        );

        $jwksUri = $this->server->getBaseUrl() . '/realms/master/protocol/openid-connect/certs';

        $fromMiss = $this->client->getJwk(
            realm: 'master',
            kid: 'kid-1',
            jwksUri: $jwksUri,
            allowToUseCache: true,
        );
        self::assertNotNull($fromMiss);
        self::assertSame('first-modulus', $fromMiss->getN());
        self::assertCount(1, $this->server->getRequests());

        $fromCache = $this->client->getJwk(
            realm: 'master',
            kid: 'kid-1',
            jwksUri: $jwksUri,
            allowToUseCache: true,
        );
        self::assertNotNull($fromCache);
        self::assertSame('first-modulus', $fromCache->getN());
        self::assertCount(1, $this->server->getRequests());

        $refreshed = $this->client->getJwk(
            realm: 'master',
            kid: 'kid-1',
            jwksUri: $jwksUri,
            allowToUseCache: false,
        );
        self::assertNotNull($refreshed);
        self::assertSame('second-modulus', $refreshed->getN());
        self::assertCount(2, $this->server->getRequests());
    }
}
