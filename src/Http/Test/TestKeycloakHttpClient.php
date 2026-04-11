<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Test;

use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
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
