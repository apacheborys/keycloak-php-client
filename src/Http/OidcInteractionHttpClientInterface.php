<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;

interface OidcInteractionHttpClientInterface
{
    public function getOpenIdConfiguration(string $realm): OpenIdConfigurationDto;

    public function getJwk(
        string $kid,
        string $jwksUri,
    ): ?JwkDto;

    public function getJwks(string $jwksUri): JwksDto;

    /**
     * @return list<KeycloakRealm>
     */
    public function getAvailableRealms(): array;

    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto;

    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto;
}
