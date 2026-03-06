<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

interface KeycloakHttpClientInterface extends
    UserManagementHttpClientInterface,
    RoleManagementHttpClientInterface,
    OidcInteractionHttpClientInterface
{
}
