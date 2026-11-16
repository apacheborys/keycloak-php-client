<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;

/**
 * OIDC discovery, client-credentials tokens, JWKS-based verification and realm listing.
 */
final class OidcDiscoveryAndTokenE2ETest extends KeycloakE2ETestCase
{
    public function testOpenIdConfigurationDescribesTheConfiguredRealm(): void
    {
        $configuration = $this->httpClient->getOpenIdConfiguration(realm: $this->realm);

        self::assertSame($this->environment->issuer(), $configuration->getIssuer());
        self::assertStringStartsWith($this->environment->baseUrl, $configuration->getJwksUri());
        self::assertSame($this->environment->issuer() . '/protocol/openid-connect/token', $configuration->getTokenEndpoint());
    }

    public function testClientCredentialsTokenIsIssuedForTheConfiguredClientAndVerifies(): void
    {
        $response = $this->httpClient->requestOidcToken(
            OidcTokenRequestDto::forClientCredentials(
                realm: $this->realm,
                clientId: $this->environment->clientId,
                clientSecret: $this->environment->clientSecret,
            ),
        );

        self::assertSame('Bearer', $response->getTokenType());
        self::assertGreaterThan(0, $response->getExpiresIn());

        $payload = $response->getAccessToken()->getPayload();
        self::assertSame($this->environment->issuer(), $payload->getIss());
        self::assertSame($this->environment->clientId, $payload->getAzp());

        self::assertTrue(
            $this->service->verifyJwt($response->getAccessToken()->getRawToken()),
            'A token freshly issued by Keycloak must verify against the realm JWKS.',
        );
    }

    public function testTamperedTokenDoesNotVerify(): void
    {
        $rawToken = $this->httpClient->requestOidcToken(
            OidcTokenRequestDto::forClientCredentials(
                realm: $this->realm,
                clientId: $this->environment->clientId,
                clientSecret: $this->environment->clientSecret,
            ),
        )->getAccessToken()->getRawToken();

        [$header, $payload, $signature] = explode('.', $rawToken);

        // Corrupt a byte in the middle of the signature. (Do not touch the last character: for an
        // RSA-2048 signature it only carries base64 padding bits, so changing it keeps the bytes equal.)
        $position = intdiv(strlen($signature), 2);
        $replacement = $signature[$position] === 'A' ? 'B' : 'A';
        $tamperedSignature = substr_replace($signature, $replacement, $position, 1);

        self::assertNotSame(
            self::base64UrlDecode($signature),
            self::base64UrlDecode($tamperedSignature),
            'Precondition: the tampered signature must decode to different bytes.',
        );
        self::assertFalse($this->service->verifyJwt($header . '.' . $payload . '.' . $tamperedSignature));
    }

    public function testWrongClientSecretIsReportedAsAuthenticationFailure(): void
    {
        $this->expectException(KeycloakAuthenticationException::class);

        $this->httpClient->requestOidcToken(
            OidcTokenRequestDto::forClientCredentials(
                realm: $this->realm,
                clientId: $this->environment->clientId,
                clientSecret: 'definitely-not-the-secret',
            ),
        );
    }

    public function testJwksExposesAtLeastOneKey(): void
    {
        $configuration = $this->httpClient->getOpenIdConfiguration(realm: $this->realm);
        $jwks = $this->httpClient->getJwks(realm: $this->realm, jwksUri: $configuration->getJwksUri());

        self::assertNotEmpty($jwks->getKeys());
    }

    public function testAvailableRealmsIncludeTheConfiguredRealm(): void
    {
        $realmNames = array_map(
            static fn (KeycloakRealm $realm): string => (string) $realm->jsonSerialize()['realm'],
            $this->httpClient->getAvailableRealms(),
        );

        self::assertContains($this->realm, $realmNames);
    }

    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        self::assertIsString($decoded);

        return $decoded;
    }
}
