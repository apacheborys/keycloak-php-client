<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;

final class ServiceTestMapper implements LocalKeycloakUserBridgeMapperInterface
{
    private ?string $capturedPlainPassword = null;

    public function __construct(
        private CreateUserProfileDto $createUserProfile,
        private OidcTokenRequestDto $tokenRequest,
        private string $realmForDeletion = 'master',
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

    public function support(KeycloakUserInterface $localUser): bool
    {
        return true;
    }

    public function getCapturedPlainPassword(): ?string
    {
        return $this->capturedPlainPassword;
    }
}
