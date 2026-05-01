<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Service\Internal\KeycloakUserLookup;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final readonly class KeycloakRoleManagementService implements KeycloakRoleManagementServiceInterface
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private LocalUserMapperResolver $mapperResolver,
        private KeycloakUserLookup $userLookup,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function synchronizeRolesOnUserCreation(KeycloakUserInterface $localUser, KeycloakUser $createdUser): void
    {
        $mapper = $this->mapperResolver->resolveForUser(localUser: $localUser);
        $realm = $mapper->getRealm(localUser: $localUser);
        $availableRoles = $this->httpClient->getRoles(
            dto: new GetRolesDto(realm: $realm),
        );

        $profileDto = $mapper->prepareLocalUserForKeycloakUserCreation(
            localUser: $localUser,
            availableRoles: $availableRoles,
        );

        $this->assertMappedRealmMatches(
            expectedRealm: $realm,
            mappedRealm: $profileDto->getRealm(),
            operation: 'synchronizeRolesOnUserCreation',
        );

        $desiredRoles = $profileDto->getRoles();
        if ($desiredRoles === []) {
            return;
        }

        $availableRoles = $this->ensureRolesExistForRealm(
            realm: $realm,
            desiredRoles: $desiredRoles,
            availableRoles: $availableRoles,
        );

        $rolesToAssign = $this->resolveRolesByName(
            desiredRoles: $desiredRoles,
            availableRoles: $availableRoles,
            strict: true,
        );

        if ($rolesToAssign === []) {
            return;
        }

        $this->httpClient->assignRolesToUser(
            dto: new AssignUserRolesDto(
                realm: $realm,
                userId: Uuid::fromString($createdUser->getKeycloakId()),
                roles: $rolesToAssign,
            ),
        );
    }

    #[Override]
    public function synchronizeRolesOnUserUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): void {
        $mapper = $this->mapperResolver->resolveForUserPair(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );

        $oldRealm = $mapper->getRealm(localUser: $oldUserVersion);
        $newRealm = $mapper->getRealm(localUser: $newUserVersion);
        if ($oldRealm !== $newRealm) {
            $this->debug(
                message: 'Role synchronization failed: old and new user versions are mapped to different realms.',
                context: [
                    'old_keycloak_user_id' => $oldUserVersion->getKeycloakId(),
                    'new_keycloak_user_id' => $newUserVersion->getKeycloakId(),
                    'old_realm' => $oldRealm,
                    'new_realm' => $newRealm,
                ],
            );

            throw new LogicException('Old and new user versions must reference the same realm.');
        }

        $availableRoles = $this->httpClient->getRoles(
            dto: new GetRolesDto(realm: $oldRealm),
        );

        $dto = $mapper->prepareLocalUserDiffForKeycloakUserUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
            availableRoles: $availableRoles,
        );

        $this->assertMappedRealmMatches(
            expectedRealm: $oldRealm,
            mappedRealm: $dto->getRealm(),
            operation: 'synchronizeRolesOnUserUpdate',
        );

        $desiredRoles = $dto->getProfile()->getRoles();
        if ($desiredRoles === null || $desiredRoles === []) {
            return;
        }

        $lookupUser = $this->selectLookupUserForUpdate(
            oldUserVersion: $oldUserVersion,
            newUserVersion: $newUserVersion,
        );
        $resolvedUserId = $this->userLookup->resolveUserId(
            realm: $oldRealm,
            localUser: $lookupUser,
            localUserIdAttributeName: $mapper->getLocalUserIdAttributeName(localUser: $lookupUser),
            operation: 'synchronizeRolesOnUserUpdate',
        );

        $availableRoles = $this->ensureRolesExistForRealm(
            realm: $oldRealm,
            desiredRoles: $desiredRoles,
            availableRoles: $availableRoles,
        );

        $resolvedDesiredRoles = $this->resolveRolesByName(
            desiredRoles: $desiredRoles,
            availableRoles: $availableRoles,
            strict: true,
        );

        $currentRoleNames = $this->normalizeRoleNames(roleNames: $oldUserVersion->getRoles());
        $desiredRoleNames = $this->extractRoleNames(roles: $resolvedDesiredRoles);

        $roleNamesToAssign = array_values(array_diff($desiredRoleNames, $currentRoleNames));
        $roleNamesToUnassign = array_values(array_diff($currentRoleNames, $desiredRoleNames));

        $rolesToAssign = $this->resolveRolesByName(
            desiredRoles: $this->roleDtosFromNames(roleNames: $roleNamesToAssign),
            availableRoles: $availableRoles,
            strict: true,
        );

        if ($rolesToAssign !== []) {
            $availableForUser = $this->httpClient->getAvailableUserRoles(
                dto: new GetUserAvailableRolesDto(
                    realm: $oldRealm,
                    userId: $resolvedUserId,
                ),
            );
            $rolesToAssign = $this->resolveRolesByName(
                desiredRoles: $rolesToAssign,
                availableRoles: $availableForUser,
                strict: true,
            );

            if ($rolesToAssign !== []) {
                $this->httpClient->assignRolesToUser(
                    dto: new AssignUserRolesDto(
                        realm: $oldRealm,
                        userId: $resolvedUserId,
                        roles: $rolesToAssign,
                    ),
                );
            }
        }

        $rolesToUnassign = $this->resolveRolesByName(
            desiredRoles: $this->roleDtosFromNames(roleNames: $roleNamesToUnassign),
            availableRoles: $availableRoles,
            strict: false,
        );

        if ($rolesToUnassign === []) {
            return;
        }

        $this->httpClient->unassignRolesFromUser(
            dto: new AssignUserRolesDto(
                realm: $oldRealm,
                userId: $resolvedUserId,
                roles: $rolesToUnassign,
            ),
        );
    }

    private function assertMappedRealmMatches(string $expectedRealm, string $mappedRealm, string $operation): void
    {
        if ($expectedRealm === $mappedRealm) {
            return;
        }

        $this->debug(
            message: 'Mapper returned realm different from mapper::getRealm().',
            context: [
                'operation' => $operation,
                'expected_realm' => $expectedRealm,
                'mapped_realm' => $mappedRealm,
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Mapper realm mismatch during %s. Expected "%s", got "%s".',
                $operation,
                $expectedRealm,
                $mappedRealm
            )
        );
    }

    private function selectLookupUserForUpdate(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion,
    ): KeycloakUserInterface {
        if ($newUserVersion->getKeycloakId() !== null || $oldUserVersion->getKeycloakId() === null) {
            return $newUserVersion;
        }

        return $oldUserVersion;
    }

    /**
     * @param list<RoleDto> $desiredRoles
     * @param list<RoleDto> $availableRoles
     * @return list<RoleDto>
     */
    private function ensureRolesExistForRealm(string $realm, array $desiredRoles, array $availableRoles): array
    {
        $availableByName = [];
        foreach ($availableRoles as $role) {
            $availableByName[$role->getName()] = $role;
        }

        $hasCreatedRoles = false;
        foreach ($this->normalizeRoles(roles: $desiredRoles) as $desiredRole) {
            $roleName = $desiredRole->getName();
            if (array_key_exists($roleName, $availableByName)) {
                continue;
            }

            $this->httpClient->createRole(
                dto: new CreateRoleDto(
                    realm: $realm,
                    role: $desiredRole,
                ),
            );
            $hasCreatedRoles = true;
            $this->debug(
                message: 'Role was created in Keycloak during role synchronization.',
                context: [
                    'realm' => $realm,
                    'role_name' => $roleName,
                ],
            );
        }

        if ($hasCreatedRoles) {
            return $this->httpClient->getRoles(
                dto: new GetRolesDto(realm: $realm),
            );
        }

        return $availableRoles;
    }

    /**
     * @param list<RoleDto> $desiredRoles
     * @param list<RoleDto> $availableRoles
     * @return list<RoleDto>
     */
    private function resolveRolesByName(array $desiredRoles, array $availableRoles, bool $strict): array
    {
        $availableByName = [];
        foreach ($availableRoles as $availableRole) {
            $availableByName[$availableRole->getName()] = $availableRole;
        }

        $resolvedRoles = [];
        foreach ($this->normalizeRoles(roles: $desiredRoles) as $desiredRole) {
            $resolvedRole = $availableByName[$desiredRole->getName()] ?? null;
            if ($resolvedRole instanceof RoleDto) {
                $resolvedRoles[] = $resolvedRole;
                continue;
            }

            if ($strict) {
                throw new LogicException(
                    message: sprintf(
                        'Role "%s" cannot be resolved in Keycloak available roles.',
                        $desiredRole->getName()
                    )
                );
            }

            $this->debug(
                message: 'Role cannot be resolved during non-strict role synchronization and will be skipped.',
                context: [
                    'role_name' => $desiredRole->getName(),
                ],
            );
        }

        return $resolvedRoles;
    }

    /**
     * @param list<string> $roleNames
     * @return list<string>
     */
    private function normalizeRoleNames(array $roleNames): array
    {
        $normalized = [];
        foreach ($roleNames as $roleName) {
            $trimmedRoleName = trim($roleName);
            if ($trimmedRoleName === '') {
                continue;
            }

            $normalized[] = $trimmedRoleName;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param list<RoleDto> $roles
     * @return list<string>
     */
    private function extractRoleNames(array $roles): array
    {
        return array_values(
            array_unique(
                array_map(
                    static fn (RoleDto $role): string => $role->getName(),
                    $roles,
                )
            )
        );
    }

    /**
     * @param list<string> $roleNames
     * @return list<RoleDto>
     */
    private function roleDtosFromNames(array $roleNames): array
    {
        return array_map(
            static fn (string $roleName): RoleDto => new RoleDto(name: $roleName),
            $this->normalizeRoleNames(roleNames: $roleNames),
        );
    }

    /**
     * @param list<RoleDto> $roles
     * @return list<RoleDto>
     */
    private function normalizeRoles(array $roles): array
    {
        $rolesByName = [];
        foreach ($roles as $role) {
            $rolesByName[$role->getName()] = $role;
        }

        return array_values($rolesByName);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        $this->logger?->debug(message: $message, context: $context);
    }
}
