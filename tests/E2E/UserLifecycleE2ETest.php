<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E;

use Apacheborys\KeycloakPhpClient\DTO\PasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\CreateRoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\Role\GetUserAvailableRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakAuthenticationException;
use Apacheborys\KeycloakPhpClient\Exception\KeycloakNotFoundException;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * The recommended integration path: a local user + mapper driven through KeycloakServiceInterface.
 */
final class UserLifecycleE2ETest extends KeycloakE2ETestCase
{
    public function testCreateFindLoginUpdateAndDeleteUserThroughTheServiceLayer(): void
    {
        $roleName = $this->uniqueName('e2e-role');
        $replacementRoleName = $this->uniqueName('e2e-role');
        foreach ([$roleName, $replacementRoleName] as $name) {
            $this->trackRole($name);
            $this->httpClient->createRole(new CreateRoleDto(realm: $this->realm, role: new RoleDto(name: $name)));
        }

        $localUser = $this->newLocalUser(roles: [$roleName]);
        $password = 'E2e-' . bin2hex(random_bytes(6)) . '!';

        // create
        $created = $this->service->createUser($localUser, new PasswordDto(plainPassword: $password));
        $this->trackUser($created->getId());

        self::assertSame($localUser->getUsername(), $created->getUsername());
        self::assertSame($localUser->getEmail(), $created->getEmail());
        self::assertSame('E2E', $created->getFirstName());
        self::assertSame('User', $created->getLastName());
        self::assertTrue($created->isEnabled());
        self::assertTrue($created->isEmailVerified());
        self::assertSame(
            [(string) $localUser->getId()],
            $created->getAttributes()[LocalKeycloakUserBridgeMapperInterface::DEFAULT_LOCAL_USER_ID_ATTRIBUTE_NAME] ?? null,
            'The mapper-provided local id attribute must be persisted on the Keycloak user.',
        );

        // the local role was synchronized on creation: it is no longer "available" (= unassigned)
        self::assertNotContains($roleName, $this->availableRoleNamesFor($created->getId()));

        // find by local-id attribute (the local user does not know its Keycloak id yet)
        $found = $this->service->findUser($localUser);
        self::assertSame($created->getKeycloakId(), $found->getKeycloakId());

        // find by id and search by email
        self::assertSame(
            $created->getKeycloakId(),
            $this->service->findUserById($this->realm, $created->getId())->getKeycloakId(),
        );
        $matches = $this->service->searchUsers(
            new SearchUsersDto(realm: $this->realm, email: $localUser->getEmail(), exact: true),
        );
        self::assertCount(1, $matches);
        self::assertSame($created->getKeycloakId(), $matches[0]->getKeycloakId());

        // login with the password that createUser() set, then verify the token
        $tokens = $this->service->loginUser($localUser, $password);
        self::assertSame($localUser->getUsername(), $tokens->getAccessToken()->getPayload()->getPreferredUsername());
        self::assertSame($created->getKeycloakId(), $tokens->getAccessToken()->getPayload()->getSub()->toString());
        self::assertTrue($this->service->verifyJwt($tokens->getAccessToken()->getRawToken()));

        // update profile and swap the role. (An empty role list means "skip role synchronization"
        // per LocalKeycloakUserBridgeMapperInterface, so removal is expressed by a different desired set.)
        $knownUser = $localUser->withKeycloakId($created->getKeycloakId());
        $updatedLocalUser = $knownUser->withName('Renamed', 'Person')->withRoles([$replacementRoleName]);
        $updated = $this->service->updateUser($knownUser, $updatedLocalUser);

        self::assertSame('Renamed', $updated->getFirstName());
        self::assertSame('Person', $updated->getLastName());
        $availableAfterUpdate = $this->availableRoleNamesFor($created->getId());
        self::assertContains($roleName, $availableAfterUpdate, 'The old role must have been unassigned.');
        self::assertNotContains($replacementRoleName, $availableAfterUpdate, 'The new role must have been assigned.');

        // delete
        $this->service->deleteUser($updatedLocalUser);

        $this->expectException(KeycloakNotFoundException::class);
        $this->service->findUserById($this->realm, $created->getId());
    }

    public function testLoginWithWrongPasswordIsReportedAsAuthenticationFailure(): void
    {
        $localUser = $this->newLocalUser();
        $created = $this->service->createUser($localUser, new PasswordDto(plainPassword: 'E2e-correct-' . bin2hex(random_bytes(4))));
        $this->trackUser($created->getId());

        $this->expectException(KeycloakAuthenticationException::class);
        $this->service->loginUser($localUser, 'E2e-wrong-password');
    }

    public function testFindUserByIdForUnknownUserThrowsNotFound(): void
    {
        $this->expectException(KeycloakNotFoundException::class);
        $this->service->findUserById($this->realm, Uuid::uuid4());
    }

    /**
     * @return list<string>
     */
    private function availableRoleNamesFor(UuidInterface $userId): array
    {
        return self::roleNames($this->httpClient->getAvailableUserRoles(
            new GetUserAvailableRolesDto(realm: $this->realm, userId: $userId),
        ));
    }
}
