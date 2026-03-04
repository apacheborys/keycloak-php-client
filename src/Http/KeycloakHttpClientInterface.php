<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;

interface KeycloakHttpClientInterface
{
    public function getUsers(SearchUsersDto $dto): array;

    public function createUser(CreateUserDto $dto): void;

    public function updateUser(UpdateUserDto $dto): void;

    public function deleteUser(DeleteUserDto $dto): void;

    public function createRealm(array $payload): array;

    public function getRoles(): array;

    public function deleteRole(string $role): void;

    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto;

    public function getJwk(
        string $realm,
        string $kid,
        string $jwksUri,
        bool $allowToUseCache = true,
    ): ?JwkDto;

    public function getJwks(string $realm, string $jwksUri): JwksDto;

    /**
     * @return KeycloakRealm[]
     */
    public function getAvailableRealms(): array;

    public function resetPassword(ResetUserPasswordDto $dto): void;

    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto;

    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto;
}
