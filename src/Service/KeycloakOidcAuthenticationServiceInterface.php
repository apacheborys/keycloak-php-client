<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;

interface KeycloakOidcAuthenticationServiceInterface
{
    public function loginUser(KeycloakUserInterface $user, string $plainPassword): OidcTokenResponseDto;

    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto;
}
