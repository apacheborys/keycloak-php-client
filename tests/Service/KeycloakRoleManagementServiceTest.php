<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\AssignUserRolesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UserRolesDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Service\Internal\KeycloakUserLookup;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Apacheborys\KeycloakPhpClient\Service\KeycloakRoleManagementService;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestMapper;
use Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures\ServiceTestUser;
use Apacheborys\KeycloakPhpClient\ValueObject\OidcGrantType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class KeycloakRoleManagementServiceTest extends TestCase
{
    public function testSynchronizeRolesOnUserCreationAssignsResolvedRoles(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $profileDto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
        $mapper = new ServiceTestMapper(
            $profileDto,
            $this->buildTokenRequestDto(),
            createUserRolesDto: new UserRolesDto(
                realm: 'master',
                roles: [new RoleDto(name: 'role-user')],
            ),
        );
        $service = $this->createService($httpClient, $mapper);

        $localUser = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');
        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );
        $role = new RoleDto(
            id: Uuid::fromString('7426cf8e-5827-4eb1-bcc7-b3eaaa703bb8'),
            name: 'role-user',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$role]);
        $httpClient->queueResult('assignRolesToUser', null);

        $service->synchronizeRolesOnUserCreation($localUser, $createdUser);

        self::assertSame(
            ['getRoles', 'assignRolesToUser'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        /** @var AssignUserRolesDto $dto */
        $dto = $httpClient->getCalls()[1]['args'][0];
        self::assertSame([$role], $dto->getRoles());
    }

    public function testSynchronizeRolesOnUserCreationCreatesMissingRoleWhenMapperReturnsRole(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $profileDto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
        $mapper = new ServiceTestMapper(
            $profileDto,
            $this->buildTokenRequestDto(),
            createUserRolesDto: new UserRolesDto(
                realm: 'master',
                roles: [new RoleDto(name: 'missing-role', description: 'Role for test')],
            ),
        );
        $service = $this->createService($httpClient, $mapper);

        $localUser = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');
        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );
        $createdRole = new RoleDto(
            id: Uuid::fromString('3e7f40af-e8d4-4ead-bb8b-b034e95ffad8'),
            name: 'missing-role',
            description: 'Role for test',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('createRole', null);
        $httpClient->queueResult('getRoles', [$createdRole]);
        $httpClient->queueResult('assignRolesToUser', null);

        $service->synchronizeRolesOnUserCreation($localUser, $createdUser);

        self::assertSame(
            ['getRoles', 'createRole', 'getRoles', 'assignRolesToUser'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testSynchronizeRolesOnUserCreationSkipsRoleSyncWhenMapperReturnsNoRoles(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $profileDto = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
        $mapper = new ServiceTestMapper($profileDto, $this->buildTokenRequestDto());
        $service = $this->createService($httpClient, $mapper);

        $localUser = new ServiceTestUser('92a372d5-c338-4e77-a1b3-08771241036e');
        $createdUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );

        $httpClient->queueResult('getRoles', []);

        $service->synchronizeRolesOnUserCreation($localUser, $createdUser);

        self::assertSame(
            ['getRoles'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testSynchronizeRolesOnUserUpdateAssignsAndUnassignsRoles(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedRolesDto = new UserRolesDto(
            realm: 'master',
            roles: [new RoleDto(name: 'role-new')],
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserRolesDto: $mappedRolesDto,
        );
        $service = $this->createService($httpClient, $mapper);

        $oldUser = new ServiceTestUser(
            keycloakId: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: ['role-old'],
        );
        $newUser = new ServiceTestUser(
            keycloakId: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: ['role-new'],
        );

        $roleOld = new RoleDto(
            id: Uuid::fromString('e95d307d-ef1c-4151-8d4b-11376ef7e307'),
            name: 'role-old',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );
        $roleNew = new RoleDto(
            id: Uuid::fromString('246657bd-17c7-4f9d-9ecf-98920f099ad6'),
            name: 'role-new',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$roleOld, $roleNew]);
        $httpClient->queueResult('getAvailableUserRoles', [$roleNew]);
        $httpClient->queueResult('assignRolesToUser', null);
        $httpClient->queueResult('unassignRolesFromUser', null);

        $service->synchronizeRolesOnUserUpdate($oldUser, $newUser);

        self::assertSame(
            ['getRoles', 'getAvailableUserRoles', 'assignRolesToUser', 'unassignRolesFromUser'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );

        /** @var AssignUserRolesDto $assignDto */
        $assignDto = $httpClient->getCalls()[2]['args'][0];
        /** @var AssignUserRolesDto $unassignDto */
        $unassignDto = $httpClient->getCalls()[3]['args'][0];

        self::assertSame([$roleNew], $assignDto->getRoles());
        self::assertSame([$roleOld], $unassignDto->getRoles());
    }

    public function testSynchronizeRolesOnUserUpdateAllowsLocalUsersWithoutStoredKeycloakId(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedRolesDto = new UserRolesDto(
            realm: 'master',
            roles: [new RoleDto(name: 'role-new')],
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserRolesDto: $mappedRolesDto,
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUser = new ServiceTestUser(keycloakId: null, roles: [], id: 'local-user-1');
        $newUser = new ServiceTestUser(keycloakId: null, roles: ['role-new'], id: 'local-user-1');
        $keycloakUser = KeycloakUser::fromArray(
            [
                'id' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'username' => 'user@example.com',
                'createdTimestamp' => 1_700_000_000_000,
            ]
        );
        $roleNew = new RoleDto(
            id: Uuid::fromString('246657bd-17c7-4f9d-9ecf-98920f099ad6'),
            name: 'role-new',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$roleNew]);
        $httpClient->queueResult('getUsers', [$keycloakUser]);
        $httpClient->queueResult('getAvailableUserRoles', [$roleNew]);
        $httpClient->queueResult('assignRolesToUser', null);

        $service->synchronizeRolesOnUserUpdate($oldUser, $newUser);

        self::assertSame(
            ['getRoles', 'getUsers', 'getAvailableUserRoles', 'assignRolesToUser'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
    }

    public function testSynchronizeRolesOnUserUpdateUsesOldKeycloakIdWhenNewVersionDoesNotExposeIt(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedRolesDto = new UserRolesDto(
            realm: 'master',
            roles: [new RoleDto(name: 'role-new')],
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserRolesDto: $mappedRolesDto,
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUser = new ServiceTestUser(
            keycloakId: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: [],
        );
        $newUser = new ServiceTestUser(
            keycloakId: null,
            roles: ['role-new'],
        );
        $roleNew = new RoleDto(
            id: Uuid::fromString('246657bd-17c7-4f9d-9ecf-98920f099ad6'),
            name: 'role-new',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$roleNew]);
        $httpClient->queueResult('getAvailableUserRoles', [$roleNew]);
        $httpClient->queueResult('assignRolesToUser', null);

        $service->synchronizeRolesOnUserUpdate($oldUser, $newUser);

        self::assertSame(
            ['getRoles', 'getAvailableUserRoles', 'assignRolesToUser'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        /** @var AssignUserRolesDto $assignDto */
        $assignDto = $httpClient->getCalls()[2]['args'][0];
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $assignDto->getUserId()->toString());
    }

    public function testSynchronizeRolesOnUserUpdateDoesNotRejectDifferentKeycloakIds(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedRolesDto = new UserRolesDto(
            realm: 'master',
            roles: [new RoleDto(name: 'role-new')],
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserRolesDto: $mappedRolesDto,
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUser = new ServiceTestUser(
            keycloakId: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: [],
        );
        $newUser = new ServiceTestUser(
            keycloakId: 'd15f15d4-c107-4a99-8281-8b2a7d7c6d6a',
            roles: ['role-new'],
        );
        $roleNew = new RoleDto(
            id: Uuid::fromString('246657bd-17c7-4f9d-9ecf-98920f099ad6'),
            name: 'role-new',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', [$roleNew]);
        $httpClient->queueResult('getAvailableUserRoles', [$roleNew]);
        $httpClient->queueResult('assignRolesToUser', null);

        $service->synchronizeRolesOnUserUpdate($oldUser, $newUser);

        /** @var AssignUserRolesDto $assignDto */
        $assignDto = $httpClient->getCalls()[2]['args'][0];
        self::assertSame('d15f15d4-c107-4a99-8281-8b2a7d7c6d6a', $assignDto->getUserId()->toString());
    }

    public function testSynchronizeRolesOnUserUpdateCreatesMissingRoleWhenMapperReturnsRole(): void
    {
        $httpClient = new TestKeycloakHttpClient();
        $mappedRolesDto = new UserRolesDto(
            realm: 'master',
            roles: [new RoleDto(name: 'missing-role', description: 'Role for test')],
        );
        $mapper = new ServiceTestMapper(
            $this->buildProfileDto(),
            $this->buildTokenRequestDto(),
            updateUserRolesDto: $mappedRolesDto,
        );
        $service = $this->createService($httpClient, $mapper);
        $oldUser = new ServiceTestUser(
            keycloakId: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: [],
        );
        $newUser = new ServiceTestUser(
            keycloakId: '92a372d5-c338-4e77-a1b3-08771241036e',
            roles: ['missing-role'],
        );
        $createdRole = new RoleDto(
            id: Uuid::fromString('3e7f40af-e8d4-4ead-bb8b-b034e95ffad8'),
            name: 'missing-role',
            description: 'Role for test',
            composite: false,
            clientRole: false,
            containerId: Uuid::fromString('992b5dcf-1cdc-4b69-8fe2-0beaec437b17'),
        );

        $httpClient->queueResult('getRoles', []);
        $httpClient->queueResult('createRole', null);
        $httpClient->queueResult('getRoles', [$createdRole]);
        $httpClient->queueResult('getAvailableUserRoles', [$createdRole]);
        $httpClient->queueResult('assignRolesToUser', null);

        $service->synchronizeRolesOnUserUpdate($oldUser, $newUser);

        self::assertSame(
            ['getRoles', 'createRole', 'getRoles', 'getAvailableUserRoles', 'assignRolesToUser'],
            array_map(static fn (array $call): string => $call['method'], $httpClient->getCalls()),
        );
        self::assertSame('missing-role', $httpClient->getCalls()[1]['args'][0]->getRole()->getName());
        /** @var AssignUserRolesDto $assignDto */
        $assignDto = $httpClient->getCalls()[4]['args'][0];
        self::assertSame([$createdRole], $assignDto->getRoles());
    }

    private function createService(
        TestKeycloakHttpClient $httpClient,
        ServiceTestMapper $mapper,
    ): KeycloakRoleManagementService {
        return new KeycloakRoleManagementService(
            httpClient: $httpClient,
            mapperResolver: new LocalUserMapperResolver([$mapper]),
            userLookup: new KeycloakUserLookup(httpClient: $httpClient),
        );
    }

    private function buildProfileDto(): CreateUserProfileDto
    {
        return new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: true,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
    }

    private function buildTokenRequestDto(): OidcTokenRequestDto
    {
        return new OidcTokenRequestDto(
            realm: 'master',
            clientId: 'backend',
            clientSecret: 'secret',
            username: 'user@example.com',
            password: 'secret',
            grantType: OidcGrantType::PASSWORD,
        );
    }
}
