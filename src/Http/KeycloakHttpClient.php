<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Override;

final readonly class KeycloakHttpClient implements KeycloakHttpClientInterface
{
    public function __construct(
        private UserManagementHttpClientInterface $userManagement,
        private RoleManagementHttpClientInterface $roleManagement,
        private OidcInteractionHttpClientInterface $oidcInteraction,
    ) {
    }

    /**
     * @return list<KeycloakUser>
     */
    #[Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        return $this->userManagement->getUsers(dto: $dto);
    }

    #[Override]
    public function createUser(CreateUserDto $dto): void
    {
        $this->userManagement->createUser(dto: $dto);
    }

    #[Override]
    public function updateUser(UpdateUserDto $dto): void
    {
        $this->userManagement->updateUser(dto: $dto);
    }

    #[Override]
    public function deleteUser(DeleteUserDto $dto): void
    {
        $this->userManagement->deleteUser(dto: $dto);
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    #[Override]
    public function createRealm(array $payload): array
    {
        return $this->userManagement->createRealm(payload: $payload);
    }

    #[Override]
    public function getRoles(GetRolesDto $dto): array
    {
        return $this->roleManagement->getRoles(dto: $dto);
    }

    #[Override]
    public function getAvailableUserRoles(GetUserAvailableRolesDto $dto): array
    {
        return $this->roleManagement->getAvailableUserRoles(dto: $dto);
    }

    #[Override]
    public function createRole(CreateRoleDto $dto): void
    {
        $this->roleManagement->createRole(dto: $dto);
    }

    #[Override]
    public function deleteRole(DeleteRoleDto $dto): void
    {
        $this->roleManagement->deleteRole(dto: $dto);
    }

    #[Override]
    public function assignRolesToUser(AssignUserRolesDto $dto): void
    {
        $this->roleManagement->assignRolesToUser(dto: $dto);
    }

    #[Override]
    public function unassignRolesFromUser(AssignUserRolesDto $dto): void
    {
        $this->roleManagement->unassignRolesFromUser(dto: $dto);
    }

    #[Override]
    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto
    {
        return $this->oidcInteraction->getOpenIdConfiguration(
            realm: $realm,
            allowToUseCache: $allowToUseCache,
        );
    }

    #[Override]
    public function getJwk(
        string $realm,
        string $kid,
        string $jwksUri,
        bool $allowToUseCache = true,
    ): ?JwkDto {
        return $this->oidcInteraction->getJwk(
            realm: $realm,
            kid: $kid,
            jwksUri: $jwksUri,
            allowToUseCache: $allowToUseCache,
        );
    }

    #[Override]
    public function getJwks(string $realm, string $jwksUri): JwksDto
    {
        return $this->oidcInteraction->getJwks(realm: $realm, jwksUri: $jwksUri);
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[Override]
    public function getAvailableRealms(): array
    {
        return $this->oidcInteraction->getAvailableRealms();
    }

    #[Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $this->userManagement->resetPassword(dto: $dto);
    }

    #[Override]
    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->oidcInteraction->requestTokenByPassword(dto: $dto);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->oidcInteraction->refreshToken(dto: $dto);
    }
}
