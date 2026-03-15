<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

interface KeycloakServiceInterface extends
    KeycloakUserManagementServiceInterface,
    KeycloakOidcAuthenticationServiceInterface,
    KeycloakJwtVerificationServiceInterface,
    KeycloakRealmServiceInterface
{
}
