<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Test;

use Apacheborys\KeycloakPhpClient\DTO\Request\Role\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\GetUserByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use LogicException;
use Override;
use Throwable;

final class TestKeycloakHttpClient implements KeycloakHttpClientInterface
{
    /**
     * @var array<string, list<mixed>>
     */
    private array $queues = [];

    /**
     * @var list<array{method: string, args: list<mixed>}>
     */
    private array $calls = [];

    public function queueResult(string $method, mixed $result): void
    {
        $this->queues[$method][] = $result;
    }

    /**
     * @return list<array{method: string, args: list<mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    #[Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function getUserById(GetUserByIdDto $dto): KeycloakUser
    {
        /** @var KeycloakUser $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function createUser(CreateUserDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function updateUser(UpdateUserDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function deleteUser(DeleteUserDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function createRealm(array $payload): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$payload]);

        return $result;
    }

    #[Override]
    public function getRoles(GetRolesDto $dto): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function getAvailableUserRoles(GetUserAvailableRolesDto $dto): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function createRole(CreateRoleDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[\Override]
    public function deleteRole(DeleteRoleDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function assignRolesToUser(AssignUserRolesDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function unassignRolesFromUser(AssignUserRolesDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    /**
     * @return list<ClientScopeDto>
     */
    #[Override]
    public function getClientScopes(GetClientScopesDto $dto): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function getClientScopeById(GetClientScopeByIdDto $dto): ClientScopeDto
    {
        /** @var ClientScopeDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    /**
     * @return list<ClientScopesProtocolMapperDto>
     */
    #[Override]
    public function getClientScopeProtocolMappers(GetClientScopeProtocolMappersDto $dto): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function createClientScope(CreateClientScopeDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function updateClientScope(UpdateClientScopeDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function deleteClientScope(DeleteClientScopeDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function createClientScopeProtocolMapper(CreateClientScopeProtocolMapperDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function updateClientScopeProtocolMapper(UpdateClientScopeProtocolMapperDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function deleteClientScopeProtocolMapper(DeleteClientScopeProtocolMapperDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function getUserProfile(GetUserProfileDto $dto): UserProfileDto
    {
        /** @var UserProfileDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function createUserProfileAttribute(CreateUserProfileAttributeDto $dto): UserProfileDto
    {
        /** @var UserProfileDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function updateUserProfileAttribute(UpdateUserProfileAttributeDto $dto): UserProfileDto
    {
        /** @var UserProfileDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function deleteUserProfileAttribute(DeleteUserProfileAttributeDto $dto): UserProfileDto
    {
        /** @var UserProfileDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function getOpenIdConfiguration(string $realm, bool $allowToUseCache = true): OpenIdConfigurationDto
    {
        /** @var OpenIdConfigurationDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$realm, $allowToUseCache]);

        return $result;
    }

    #[Override]
    public function getJwk(
        string $realm,
        string $kid,
        string $jwksUri,
        bool $allowToUseCache = true,
    ): ?JwkDto {
        /** @var ?JwkDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$realm, $kid, $jwksUri, $allowToUseCache]);

        return $result;
    }

    #[Override]
    public function getJwks(string $realm, string $jwksUri): JwksDto
    {
        /** @var JwksDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$realm, $jwksUri]);

        return $result;
    }

    #[Override]
    public function getAvailableRealms(): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: []);

        return $result;
    }

    #[Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        /** @var OidcTokenResponseDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[\Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        /** @var OidcTokenResponseDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    /**
     * @param list<mixed> $args
     */
    private function nextResult(string $method, array $args): mixed
    {
        $this->calls[] = [
            'method' => $method,
            'args' => $args,
        ];

        if (empty($this->queues[$method])) {
            throw new LogicException("No queued result for {$method}()");
        }

        $result = array_shift($this->queues[$method]);

        if ($result instanceof Throwable) {
            throw $result;
        }

        return $result;
    }
}
