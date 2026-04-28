<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures;

use Apacheborys\KeycloakPhpClient\DTO\PreparedUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Ramsey\Uuid\Uuid;

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
        private bool $roleCreationAllowed = false,
    ) {
    }

    public function getRealm(KeycloakUserInterface $localUser): string
    {
        return $this->createUserProfile->getRealm();
    }

    /**
     * @param list<RoleDto> $availableRoles
     */
    public function prepareRolesForUser(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): PreparedUserRolesDto {
        $roles = array_map(
            static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
            $localUser->getRoles(),
        );

        if ($roles === []) {
            $roles = $this->createUserProfile->getRoles();
        }

        return new PreparedUserRolesDto(
            roles: $roles,
            roleCreationAllowed: $this->roleCreationAllowed,
        );
    }

    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser,
        array $availableRoles
    ): CreateUserProfileDto {
        /** @var list<RoleDto> $availableRoles */
        $this->capturedAvailableRolesForCreation = $availableRoles;

        return $this->createUserProfile;
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
        return new DeleteUserDto(
            realm: $this->realmForDeletion,
            userId: Uuid::fromString($localUser->getKeycloakId()),
        );
    }

    public function prepareLocalUserDiffForKeycloakUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
        array $availableRoles
    ): UpdateUserDto {
        $this->capturedOldUserForUpdate = $oldUserVersion;
        $this->capturedNewUserForUpdate = $newUserVersion;
        /** @var list<RoleDto> $availableRoles */
        $this->capturedAvailableRolesForUpdate = $availableRoles;

        if ($this->updateUserDto !== null) {
            return $this->updateUserDto;
        }

        return new UpdateUserDto(
            realm: $this->realmForDeletion,
            userId: Uuid::fromString($newUserVersion->getKeycloakId()),
            profile: new UpdateUserProfileDto(
                username: $newUserVersion->getUsername(),
                email: $newUserVersion->getEmail(),
                emailVerified: $newUserVersion->isEmailVerified(),
                enabled: $newUserVersion->isEnabled(),
                firstName: $newUserVersion->getFirstName(),
                lastName: $newUserVersion->getLastName(),
                roles: array_map(
                    static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
                    $newUserVersion->getRoles(),
                ),
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
