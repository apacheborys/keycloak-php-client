<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientFactory;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class KeycloakHttpClientFactoryTest extends TestCase
{
    public function testCreateReturnsFacadeClient(): void
    {
        $factory = new KeycloakHttpClientFactory();
        $config = new KeycloakClientConfig(
            baseUrl: 'http://localhost:8080',
            clientRealm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
        );

        $client = $factory->create(
            config: $config,
            httpClient: $this->createStub(ClientInterface::class),
            requestFactory: $this->createStub(RequestFactoryInterface::class),
            streamFactory: $this->createStub(StreamFactoryInterface::class),
        );

        self::assertInstanceOf(KeycloakHttpClientInterface::class, $client);
        self::assertInstanceOf(KeycloakHttpClient::class, $client);
    }

    public function testCreateForTestReturnsTestClient(): void
    {
        $factory = new KeycloakHttpClientFactory();

        self::assertInstanceOf(TestKeycloakHttpClient::class, $factory->createForTest());
    }
}
