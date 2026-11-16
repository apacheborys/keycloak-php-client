<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\UserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use Ramsey\Uuid\Uuid;

/**
 * A straightforward mapper, shaped like one an application would write:
 * every value is derived from the local user, nothing is captured or stubbed.
 */
final readonly class E2EUserMapper implements LocalKeycloakUserBridgeMapperInterface
{
    public function __construct(
        private E2EConfig $config,
        private string $localUserIdAttributeName,
    ) {
    }

    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->config->realm;
    }

    public function getLocalUserIdAttribute(KeycloakUserInterface $localUser): AttributeValueDto
    {
        return new AttributeValueDto(
            attributeName: $this->localUserIdAttributeName,
            attributeValue: $localUser->getId(),
        );
    }

    public function prepareLocalUserForKeycloakUserCreation(KeycloakUserInterface $localUser): CreateUserProfileDto
    {
        return new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
            realm: $this->config->realm,
            attributes: [$this->getLocalUserIdAttribute($localUser)],
        );
    }

    public function prepareLocalUserRolesForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles,
    ): UserRolesDto {
        return $this->rolesOf($localUser);
    }

    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword,
    ): OidcTokenRequestDto {
        return new OidcTokenRequestDto(
            realm: $this->config->realm,
            clientId: $this->config->clientId,
            clientSecret: $this->config->clientSecret,
            username: $localUser->getUsername(),
            password: $plainPassword,
            grantType: OidcGrantType::PASSWORD,
        );
    }

    public function prepareLocalUserForKeycloakUserDeletion(KeycloakUserInterface $localUser): DeleteUserDto
    {
        $keycloakId = $localUser->getKeycloakId();

        return new DeleteUserDto(
            realm: $this->config->realm,
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $localUser->getId(),
        );
    }

    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
    ): UpdateUserDto {
        $keycloakId = $newUserVersion->getKeycloakId();

        return new UpdateUserDto(
            realm: $this->config->realm,
            profile: new UpdateUserProfileDto(
                username: $newUserVersion->getUsername(),
                email: $newUserVersion->getEmail(),
                emailVerified: $newUserVersion->isEmailVerified(),
                enabled: $newUserVersion->isEnabled(),
                firstName: $newUserVersion->getFirstName(),
                lastName: $newUserVersion->getLastName(),
                // Keycloak replaces the attribute map on update, so the identifier must be resent.
                attributes: [$this->getLocalUserIdAttribute($newUserVersion)],
            ),
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $newUserVersion->getId(),
        );
    }

    public function prepareLocalUserRolesForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles,
    ): UserRolesDto {
        return $this->rolesOf($newUserVersion);
    }

    public function support(KeycloakUserInterface $localUser): bool
    {
        return true;
    }

    private function rolesOf(KeycloakUserInterface $localUser): UserRolesDto
    {
        return new UserRolesDto(
            realm: $this->config->realm,
            roles: array_map(
                static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
                array_values($localUser->getRoles()),
            ),
        );
    }
}
