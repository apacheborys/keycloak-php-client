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
use Override;
use Ramsey\Uuid\Uuid;

/**
 * A realistic mapper implementation: every DTO is derived from the local user,
 * the way an application would do it. Used by the service-layer E2E tests.
 */
final readonly class E2EUserMapper implements LocalKeycloakUserBridgeMapperInterface
{
    public function __construct(
        private string $realm,
        private string $clientId,
        private string $clientSecret,
    ) {
    }

    #[Override]
    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->realm;
    }

    #[Override]
    public function getLocalUserIdAttribute(KeycloakUserInterface $localUser): AttributeValueDto
    {
        return new AttributeValueDto(
            attributeName: self::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME,
            attributeValue: (string) $localUser->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserCreation(KeycloakUserInterface $localUser): CreateUserProfileDto
    {
        $attributes = [self::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME => (string) $localUser->getId()];
        if ($localUser instanceof E2ELocalUser) {
            $attributes += $localUser->getExtraAttributes();
        }

        return new CreateUserProfileDto(
            username: $localUser->getUsername(),
            email: $localUser->getEmail(),
            emailVerified: $localUser->isEmailVerified(),
            enabled: $localUser->isEnabled(),
            firstName: $localUser->getFirstName(),
            lastName: $localUser->getLastName(),
            realm: $this->realm,
            attributes: $attributes,
        );
    }

    #[Override]
    public function prepareLocalUserRolesForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): UserRolesDto {
        return $this->rolesFor($localUser);
    }

    #[Override]
    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword
    ): OidcTokenRequestDto {
        return OidcTokenRequestDto::forPasswordGrant(
            realm: $this->realm,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            username: $localUser->getUsername(),
            password: $plainPassword,
        );
    }

    #[Override]
    public function prepareLocalUserForKeycloakUserDeletion(KeycloakUserInterface $localUser): DeleteUserDto
    {
        $keycloakId = $localUser->getKeycloakId();

        return new DeleteUserDto(
            realm: $this->realm,
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $localUser->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): UpdateUserDto {
        $keycloakId = $newUserVersion->getKeycloakId() ?? $oldUserVersion->getKeycloakId();

        return new UpdateUserDto(
            realm: $this->realm,
            profile: new UpdateUserProfileDto(
                username: $newUserVersion->getUsername(),
                email: $newUserVersion->getEmail(),
                emailVerified: $newUserVersion->isEmailVerified(),
                enabled: $newUserVersion->isEnabled(),
                firstName: $newUserVersion->getFirstName(),
                lastName: $newUserVersion->getLastName(),
            ),
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $newUserVersion->getId(),
        );
    }

    #[Override]
    public function prepareLocalUserRolesForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UserRolesDto {
        return $this->rolesFor($newUserVersion);
    }

    #[Override]
    public function support(KeycloakUserInterface $localUser): bool
    {
        return $localUser instanceof E2ELocalUser;
    }

    private function rolesFor(KeycloakUserInterface $localUser): UserRolesDto
    {
        return new UserRolesDto(
            realm: $this->realm,
            roles: array_map(
                static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
                $localUser->getRoles(),
            ),
        );
    }
}
