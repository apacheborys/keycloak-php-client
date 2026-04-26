<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Override;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakService implements KeycloakServiceInterface
{
    public function __construct(
        private KeycloakUserManagementServiceInterface $userManagementService,
        private KeycloakRoleManagementServiceInterface $roleManagementService,
        private KeycloakUserIdentifierAttributeServiceInterface $userIdentifierAttributeService,
        private KeycloakOidcAuthenticationServiceInterface $oidcAuthenticationService,
        private KeycloakJwtVerificationServiceInterface $jwtVerificationService,
        private KeycloakRealmServiceInterface $realmService,
        private LocalUserMapperResolver $mapperResolver,
    ) {
    }

    #[Override]
    public function createUser(KeycloakUserInterface $localUser, PasswordDto $passwordDto): KeycloakUser
    {
        $createdUser = $this->userManagementService->createUser(localUser: $localUser, passwordDto: $passwordDto);
        $this->roleManagementService->synchronizeRolesOnUserCreation(localUser: $localUser, createdUser: $createdUser);

        $mapper = $this->mapperResolver->resolveForUser(localUser: $localUser);
        $realm = $mapper->getRealm(localUser: $localUser);

        return $this->userManagementService->findUserById(
            realm: $realm,
            userId: Uuid::fromString($createdUser->getKeycloakId()),
        );
    }

    #[Override]
    public function findUser(KeycloakUserInterface $localUser): KeycloakUser
    {
        return $this->userManagementService->findUser(localUser: $localUser);
    }

    #[Override]
    public function findUserById(string $realm, UuidInterface $userId): KeycloakUser
    {
        return $this->userManagementService->findUserById(realm: $realm, userId: $userId);
    }

    #[Override]
    public function updateUser(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): KeycloakUser {
        $updatedUser = $this->userManagementService->updateUser(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );

        $this->roleManagementService->synchronizeRolesOnUserUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );

        $mapper = $this->mapperResolver->resolveForUserPair(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
        $realm = $mapper->getRealm(localUser: $newUserVersion);

        return $this->userManagementService->findUserById(
            realm: $realm,
            userId: Uuid::fromString($updatedUser->getKeycloakId()),
        );
    }

    #[Override]
    public function deleteUser(KeycloakUserInterface $user): void
    {
        $this->userManagementService->deleteUser(user: $user);
    }

    #[Override]
    public function ensureUserIdentifierAttribute(
        string $realm,
        EnsureUserIdentifierAttributeDto $dto
    ): void {
        $this->userIdentifierAttributeService->ensureUserIdentifierAttribute(
            realm: $realm,
            dto: $dto,
        );
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[Override]
    public function getAvailableRealms(): array
    {
        return $this->realmService->getAvailableRealms();
    }

    #[Override]
    public function verifyJwt(string $jwt): bool
    {
        return $this->jwtVerificationService->verifyJwt(jwt: $jwt);
    }

    #[Override]
    public function loginUser(KeycloakUserInterface $user, string $plainPassword): OidcTokenResponseDto
    {
        return $this->oidcAuthenticationService->loginUser(user: $user, plainPassword: $plainPassword);
    }

    #[Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->oidcAuthenticationService->refreshToken(dto: $dto);
    }
}
