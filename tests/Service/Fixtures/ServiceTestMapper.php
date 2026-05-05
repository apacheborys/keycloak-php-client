<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\UserRolesDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ServiceTestMapper implements LocalKeycloakUserBridgeMapperInterface
{
    private ?string $capturedPlainPassword = null;
    private ?KeycloakUserInterface $capturedOldUserForUpdate = null;
    private ?KeycloakUserInterface $capturedNewUserForUpdate = null;
    /**
     * @var list<RoleDto>
     */
    private array $capturedAvailableRolesForCreation = [];
    /**
     * @var list<RoleDto>
     */
    private array $capturedAvailableRolesForUpdate = [];

    public function __construct(
        private CreateUserProfileDto $createUserProfile,
        private OidcTokenRequestDto $tokenRequest,
        private string $realmForDeletion = 'master',
        private ?UpdateUserDto $updateUserDto = null,
        private string $localUserIdAttributeName = self::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME,
        private int|string|UuidInterface|null $localUserIdAttributeValue = null,
        private ?UserRolesDto $createUserRolesDto = null,
        private ?UserRolesDto $updateUserRolesDto = null,
    ) {
    }

    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->createUserProfile->getRealm();
    }

    public function getLocalUserIdAttribute(
        KeycloakUserInterface $localUser
    ): AttributeValueDto
    {
        return new AttributeValueDto(
            attributeName: $this->localUserIdAttributeName,
            attributeValue: $this->localUserIdAttributeValue ?? $localUser->getId(),
        );
    }

    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto {
        return $this->createUserProfile;
    }

    public function prepareLocalUserRolesForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): UserRolesDto {
        /** @var list<RoleDto> $availableRoles */
        $this->capturedAvailableRolesForCreation = $availableRoles;

        if ($this->createUserRolesDto !== null) {
            return $this->createUserRolesDto;
        }

        return new UserRolesDto(
            realm: $this->createUserProfile->getRealm(),
            roles: array_map(
                static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
                $localUser->getRoles(),
            ),
        );
    }

    public function prepareLocalUserForKeycloakLoginUser(
        KeycloakUserInterface $localUser,
        string $plainPassword,
    ): OidcTokenRequestDto {
        $this->capturedPlainPassword = $plainPassword;

        return $this->tokenRequest;
    }

    public function prepareLocalUserForKeycloakUserDeletion(
        KeycloakUserInterface $localUser
    ): DeleteUserDto {
        $keycloakId = $localUser->getKeycloakId();

        return new DeleteUserDto(
            realm: $this->realmForDeletion,
            userId: $keycloakId !== null ? Uuid::fromString($keycloakId) : null,
            localUserId: $localUser->getId(),
        );
    }

    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): UpdateUserDto {
        $this->capturedOldUserForUpdate = $oldUserVersion;
        $this->capturedNewUserForUpdate = $newUserVersion;

        if ($this->updateUserDto !== null) {
            return $this->updateUserDto;
        }

        $keycloakId = $newUserVersion->getKeycloakId();

        return new UpdateUserDto(
            realm: $this->realmForDeletion,
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

    public function prepareLocalUserRolesForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UserRolesDto {
        /** @var list<RoleDto> $availableRoles */
        $this->capturedAvailableRolesForUpdate = $availableRoles;

        if ($this->updateUserRolesDto !== null) {
            return $this->updateUserRolesDto;
        }

        return new UserRolesDto(
            realm: $this->realmForDeletion,
            roles: array_map(
                static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
                $newUserVersion->getRoles(),
            ),
        );
    }

    public function support(KeycloakUserInterface $localUser): bool
    {
        return true;
    }

    public function getCapturedPlainPassword(): ?string
    {
        return $this->capturedPlainPassword;
    }

    public function getCapturedOldUserForUpdate(): ?KeycloakUserInterface
    {
        return $this->capturedOldUserForUpdate;
    }

    public function getCapturedNewUserForUpdate(): ?KeycloakUserInterface
    {
        return $this->capturedNewUserForUpdate;
    }

    /**
     * @return list<RoleDto>
     */
    public function getCapturedAvailableRolesForCreation(): array
    {
        return $this->capturedAvailableRolesForCreation;
    }

    /**
     * @return list<RoleDto>
     */
    public function getCapturedAvailableRolesForUpdate(): array
    {
        return $this->capturedAvailableRolesForUpdate;
    }
}
