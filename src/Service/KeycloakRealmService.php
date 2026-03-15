<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Override;

final readonly class KeycloakRealmService implements KeycloakRealmServiceInterface
{
    public function __construct(private KeycloakHttpClientInterface $httpClient)
    {
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[Override]
    public function getAvailableRealms(): array
    {
        return $this->httpClient->getAvailableRealms();
    }
}
