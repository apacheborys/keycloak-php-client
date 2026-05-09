<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\UserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\CreateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\DeleteUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\GetUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Realm\UserProfile\UpdateUserProfileAttributeDto;
use Apacheborys\KeycloakPhpClient\Http\RealmSettingsManagementHttpClientInterface;
use RuntimeException;

final readonly class RealmSettingsManagementHttpClient implements RealmSettingsManagementHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProvider $accessTokenProvider,
    ) {
    }

    #[\Override]
    public function getUserProfile(GetUserProfileDto $dto): UserProfileDto
    {
        return $this->fetchUserProfile(realm: $dto->getRealm());
    }

    #[\Override]
    public function createUserProfileAttribute(CreateUserProfileAttributeDto $dto): UserProfileDto
    {
        $profile = $this->fetchUserProfile(realm: $dto->getRealm());
        $attributeName = $dto->getAttribute()->getName();
        if ($profile->hasAttribute(attributeName: $attributeName)) {
            throw new RuntimeException(
                sprintf('User profile attribute "%s" already exists in realm "%s".', $attributeName, $dto->getRealm())
            );
        }

        return $this->saveUserProfile(
            realm: $dto->getRealm(),
            profile: $profile->withAppendedAttribute(attribute: $dto->getAttribute()),
        );
    }

    #[\Override]
    public function updateUserProfileAttribute(UpdateUserProfileAttributeDto $dto): UserProfileDto
    {
        $profile = $this->fetchUserProfile(realm: $dto->getRealm());
        $attributeName = $dto->getAttribute()->getName();
        if (!$profile->hasAttribute(attributeName: $attributeName)) {
            throw new RuntimeException(
                sprintf('User profile attribute "%s" was not found in realm "%s".', $attributeName, $dto->getRealm())
            );
        }

        return $this->saveUserProfile(
            realm: $dto->getRealm(),
            profile: $profile->withUpdatedAttribute(attribute: $dto->getAttribute()),
        );
    }

    #[\Override]
    public function deleteUserProfileAttribute(DeleteUserProfileAttributeDto $dto): UserProfileDto
    {
        $profile = $this->fetchUserProfile(realm: $dto->getRealm());
        if (!$profile->hasAttribute(attributeName: $dto->getAttributeName())) {
            throw new RuntimeException(
                sprintf(
                    'User profile attribute "%s" was not found in realm "%s".',
                    $dto->getAttributeName(),
                    $dto->getRealm()
                )
            );
        }

        return $this->saveUserProfile(
            realm: $dto->getRealm(),
            profile: $profile->withoutAttribute(attributeName: $dto->getAttributeName()),
        );
    }

    private function fetchUserProfile(string $realm): UserProfileDto
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $realm . '/users/profile');
        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Accept' => 'application/json',
            ],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static fn (array $data): UserProfileDto => UserProfileDto::fromArray(data: $data),
        );
    }

    private function saveUserProfile(string $realm, UserProfileDto $profile): UserProfileDto
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $endpoint = $this->httpCore->buildEndpoint(path: '/admin/realms/' . $realm . '/users/profile');

        /** @var string $payload */
        $payload = json_encode(value: $profile->toArray(), flags: JSON_THROW_ON_ERROR);

        $request = $this->httpCore->createRequest(
            method: 'PUT',
            endpoint: $endpoint,
            headers: [
                'Authorization' => 'Bearer ' . $token->getRawToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $body = (string) $response->getBody();

        if ($body === '') {
            $this->httpCore->assertSuccessfulResponse(
                request: $request,
                response: $response,
            );

            return $profile;
        }

        return $this->httpCore->mapJsonResponse(
            request: $request,
            response: $response,
            mapper: static fn (array $data): UserProfileDto => UserProfileDto::fromArray(data: $data),
        );
    }
}
