<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\UpdateUserProfileAttributeDto;

interface RealmSettingsManagementHttpClientInterface
{
    public function getUserProfile(GetUserProfileDto $dto): UserProfileDto;

    public function createUserProfileAttribute(CreateUserProfileAttributeDto $dto): UserProfileDto;

    public function updateUserProfileAttribute(UpdateUserProfileAttributeDto $dto): UserProfileDto;

    public function deleteUserProfileAttribute(DeleteUserProfileAttributeDto $dto): UserProfileDto;
}
