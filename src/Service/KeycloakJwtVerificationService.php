<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Override;
use Psr\Log\LoggerInterface;

final readonly class KeycloakJwtVerificationService implements KeycloakJwtVerificationServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function verifyJwt(string $jwt): bool
    {
        $jwtFingerprint = sha1(string: $jwt);

        try {
            $token = JsonWebToken::fromRawToken(rawToken: $jwt);
        } catch (\Throwable $exception) {
            $this->debug(
                message: 'JWT verification failed: unable to parse token.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'exception_message' => $exception->getMessage(),
                ],
            );

            return false;
        }

        $temporalFailureReason = null;
        if (!$this->verifyTemporalClaims(token: $token, failureReason: $temporalFailureReason)) {
            $this->debug(
                message: 'JWT verification failed: temporal claims check failed.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'reason' => $temporalFailureReason,
                    'exp' => $token->getPayload()->getExp()->getTimestamp(),
                    'iat' => $token->getPayload()->getIat()->getTimestamp(),
                ],
            );

            return false;
        }

        $realm = $this->extractRealmFromIssuer(issuer: $token->getPayload()->getIss());
        if ($realm === null) {
            $this->debug(
                message: 'JWT verification failed: realm cannot be extracted from token issuer.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'issuer' => $token->getPayload()->getIss(),
                ],
            );

            return false;
        }

        try {
            $openIdConfiguration = $this->httpClient->getOpenIdConfiguration(realm: $realm);

            $jwk = $this->httpClient->getJwk(
                realm: $realm,
                kid: $token->getHeader()->getKid(),
                jwksUri: $openIdConfiguration->getJwksUri(),
            );
        } catch (\Throwable $exception) {
            $this->debug(
                message: 'JWT verification failed: unable to obtain OpenID configuration or JWK.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'realm' => $realm,
                    'kid' => $token->getHeader()->getKid(),
                    'exception_message' => $exception->getMessage(),
                ],
            );

            return false;
        }

        if (!$jwk instanceof JwkDto) {
            $this->debug(
                message: 'JWT verification failed: JWK was not found for token kid.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'realm' => $realm,
                    'kid' => $token->getHeader()->getKid(),
                ],
            );

            return false;
        }

        if (
            !$this->verifySignatureWithJwk(
                jwt: $jwt,
                algorithm: $token->getHeader()->getAlg(),
                jwk: $jwk
            )
        ) {
            $this->debug(
                message: 'JWT verification failed: signature verification returned false.',
                context: [
                    'jwt_fingerprint' => $jwtFingerprint,
                    'realm' => $realm,
                    'kid' => $token->getHeader()->getKid(),
                    'alg' => $token->getHeader()->getAlg(),
                ],
            );

            return false;
        }

        $this->debug(
            message: 'JWT verification succeeded.',
            context: [
                'jwt_fingerprint' => $jwtFingerprint,
                'realm' => $realm,
                'kid' => $token->getHeader()->getKid(),
            ],
        );

        return true;
    }

    private function verifyTemporalClaims(JsonWebToken $token, ?string &$failureReason = null): bool
    {
        $now = time();

        if ($token->getPayload()->getExp()->getTimestamp() <= $now) {
            $failureReason = 'token_expired';
            return false;
        }

        if ($token->getPayload()->getIat()->getTimestamp() > ($now + 300)) {
            $failureReason = 'token_issued_in_future';
            return false;
        }

        return true;
    }

    private function extractRealmFromIssuer(string $issuer): ?string
    {
        $path = parse_url(url: $issuer, component: PHP_URL_PATH);
        if (!is_string(value: $path) || $path === '') {
            return null;
        }

        $segments = explode(separator: '/', string: trim(string: $path, characters: '/'));

        foreach ($segments as $index => $segment) {
            if ($segment !== 'realms') {
                continue;
            }

            $realm = $segments[$index + 1] ?? null;
            if (!is_string($realm) || $realm === '') {
                return null;
            }

            return $realm;
        }

        return null;
    }

    private function verifySignatureWithJwk(string $jwt, string $algorithm, JwkDto $jwk): bool
    {
        $opensslAlgorithm = $this->resolveOpenSslAlgorithm(algorithm: $algorithm);
        if ($opensslAlgorithm === null) {
            return false;
        }

        $certificate = $jwk->getFirstCertificate();
        if ($certificate === null) {
            return false;
        }

        $pem = $this->buildCertificatePem(certificate: $certificate);
        $publicKey = openssl_pkey_get_public(public_key: $pem);

        if ($publicKey === false) {
            return false;
        }

        $parts = explode(separator: '.', string: $jwt);
        if (count(value: $parts) !== 3) {
            return false;
        }

        $signature = $this->decodeBase64Url(value: $parts[2]);
        if ($signature === null) {
            return false;
        }

        $input = $parts[0] . '.' . $parts[1];
        $result = openssl_verify(
            data: $input,
            signature: $signature,
            public_key: $publicKey,
            algorithm: $opensslAlgorithm
        );

        return $result === 1;
    }

    private function resolveOpenSslAlgorithm(string $algorithm): ?int
    {
        return match ($algorithm) {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => null,
        };
    }

    private function buildCertificatePem(string $certificate): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(string: $certificate, length: 64, separator: "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function decodeBase64Url(string $value): ?string
    {
        $remainder = strlen(string: $value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat(string: '=', times: 4 - $remainder);
        }

        $decoded = base64_decode(string: strtr(string: $value, from: '-_', to: '+/'), strict: true);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        $this->logger?->debug(message: $message, context: $context);
    }
}
