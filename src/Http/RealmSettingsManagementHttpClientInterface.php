<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateUserProfileAttributeDto;

interface RealmSettingsManagementHttpClientInterface
{
    public function getUserProfile(GetUserProfileDto $dto): UserProfileDto;

    public function createUserProfileAttribute(CreateUserProfileAttributeDto $dto): UserProfileDto;

    public function updateUserProfileAttribute(UpdateUserProfileAttributeDto $dto): UserProfileDto;

    public function deleteUserProfileAttribute(DeleteUserProfileAttributeDto $dto): UserProfileDto;
}
