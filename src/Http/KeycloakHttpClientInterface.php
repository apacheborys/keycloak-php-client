<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;

interface KeycloakHttpClientInterface extends
    UserManagementHttpClientInterface,
    RoleManagementHttpClientInterface
{
    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto;

    public function getJwk(
        string $realm,
        string $kid,
        string $jwksUri,
        bool $allowToUseCache = true,
    ): ?JwkDto;

    public function getJwks(string $realm, string $jwksUri): JwksDto;

    /**
     * @return list<KeycloakRealm>
     */
    public function getAvailableRealms(): array;

    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto;

    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto;
}
