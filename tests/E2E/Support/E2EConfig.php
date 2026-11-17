<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakClientConfig;

/**
 * What functional E2E tests are allowed to use: the disposable realm and the API client
 * that KeycloakTestStand provisioned for this run. No administrator credentials here.
 */
final readonly class E2EConfig
{
    /**
     * @param list<string> $passwordHashingProviders provider ids reported by the server, e.g. "pbkdf2-sha512", "bcrypt"
     */
    public function __construct(
        public string $baseUrl,
        public string $realm,
        public string $clientId,
        public string $clientSecret,
        public array $passwordHashingProviders,
    ) {
    }

    public function toClientConfig(?string $clientSecret = null): KeycloakClientConfig
    {
        return new KeycloakClientConfig(
            baseUrl: $this->baseUrl,
            clientRealm: $this->realm,
            clientId: $this->clientId,
            clientSecret: $clientSecret ?? $this->clientSecret,
        );
    }

    public function supportsPasswordHashing(string $providerId): bool
    {
        return in_array($providerId, $this->passwordHashingProviders, true);
    }
}
