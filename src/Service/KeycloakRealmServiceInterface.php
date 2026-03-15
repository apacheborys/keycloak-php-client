<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;

interface KeycloakRealmServiceInterface
{
    /**
     * @return list<KeycloakRealm>
     */
    public function getAvailableRealms(): array;
}
