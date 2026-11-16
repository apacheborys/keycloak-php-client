<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\Request\User\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;

/**
 * Smoke checks that the configured API client can talk to Keycloak at all.
 * If these fail, fix the setup from docs/e2e-testing.md before looking at other E2E tests.
 */
#[Group('e2e')]
final class ConnectivityE2ETest extends E2ETestCase
{
    public function testClientCredentialsTokenIsIssuedForConfiguredClient(): void
    {
        $token = self::createTokenProvider()->getAccessToken();
        $payload = $token->getPayload();

        self::assertNotSame('', $token->getRawToken());
        self::assertSame(self::$config->baseUrl . '/realms/' . self::$config->realm, $payload->getIss());
        self::assertSame(self::$config->clientId, $payload->getAzp());
    }

    public function testIssuedTokenPassesSignatureVerification(): void
    {
        $service = self::createService(httpClient: self::createHttpClient(), mappers: []);
        $rawToken = self::createTokenProvider()->getAccessToken()->getRawToken();

        self::assertTrue($service->verifyJwt($rawToken));
        self::assertFalse($service->verifyJwt(self::tamperSignature($rawToken)));
    }

    public function testOpenIdConfigurationAndJwksAreDiscovered(): void
    {
        $httpClient = self::createHttpClient();

        $configuration = $httpClient->getOpenIdConfiguration(realm: self::$config->realm);
        self::assertSame(self::$config->baseUrl . '/realms/' . self::$config->realm, $configuration->getIssuer());
        self::assertContains('client_credentials', $configuration->getGrantTypesSupported());

        $jwks = $httpClient->getJwks(realm: self::$config->realm, jwksUri: $configuration->getJwksUri());
        self::assertNotEmpty($jwks->getKeys());
    }

    public function testAvailableRealmsIncludeConfiguredRealm(): void
    {
        $realmNames = array_map(
            static fn (KeycloakRealm $realm): mixed => $realm->jsonSerialize()['realm'],
            self::createHttpClient()->getAvailableRealms(),
        );

        self::assertContains(self::$config->realm, $realmNames);
    }

    public function testWrongClientSecretIsReportedAsAuthenticationFailure(): void
    {
        $this->expectException(KeycloakAuthenticationException::class);

        self::createTokenProvider(clientSecret: 'definitely-not-the-secret-' . self::$runId)->getAccessToken();
    }

    public function testUnknownUserIsReportedAsNotFound(): void
    {
        $this->expectException(KeycloakNotFoundException::class);

        self::createHttpClient()->getUserById(
            new GetUserByIdDto(realm: self::$config->realm, userId: Uuid::uuid4()),
        );
    }

    private static function tamperSignature(string $rawToken): string
    {
        [$header, $payload, $signature] = explode('.', $rawToken);
        $replacement = $signature[0] === 'A' ? 'B' : 'A';

        return $header . '.' . $payload . '.' . $replacement . substr($signature, 1);
    }
}
