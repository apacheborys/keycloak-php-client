<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;

final class ServiceTestMapper implements LocalKeycloakUserBridgeMapperInterface
{
    private ?string $capturedPlainPassword = null;
    private ?KeycloakUserInterface $capturedOldUserForUpdate = null;
    private ?KeycloakUserInterface $capturedNewUserForUpdate = null;

    public function __construct(
        private CreateUserProfileDto $createUserProfile,
        private OidcTokenRequestDto $tokenRequest,
        private string $realmForDeletion = 'master',
        private ?UpdateUserDto $updateUserDto = null,
    ) {
    }

    public function prepareLocalUserForKeycloakUserCreation(
        KeycloakUserInterface $localUser
    ): CreateUserProfileDto {
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
            userId: $localUser->getId(),
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

        return new UpdateUserDto(
            realm: $this->realmForDeletion,
            userId: $newUserVersion->getId(),
            profile: new UpdateUserProfileDto(
                username: $newUserVersion->getUsername(),
                email: $newUserVersion->getEmail(),
                emailVerified: $newUserVersion->isEmailVerified(),
                enabled: $newUserVersion->isEnabled(),
                firstName: $newUserVersion->getFirstName(),
                lastName: $newUserVersion->getLastName(),
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
}
