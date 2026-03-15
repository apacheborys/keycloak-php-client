<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Override;

final readonly class KeycloakOidcAuthenticationService implements KeycloakOidcAuthenticationServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private LocalUserMapperResolver $mapperResolver,
    ) {
    }

    #[Override]
    public function loginUser(KeycloakUserInterface $user, string $plainPassword): OidcTokenResponseDto
    {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $user);
        $loginDto = $mapper->prepareLocalUserForKeycloakLoginUser(localUser: $user, plainPassword: $plainPassword);

        return $this->httpClient->requestTokenByPassword(dto: $loginDto);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->httpClient->refreshToken($dto);
    }
}
