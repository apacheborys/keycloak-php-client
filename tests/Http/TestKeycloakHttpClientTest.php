<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\Http\Test\TestKeycloakHttpClient;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestKeycloakHttpClientTest extends TestCase
{
    public function testQueuesResultsAndTracksCalls(): void
    {
        $client = new TestKeycloakHttpClient();
        $dto = new SearchUsersDto(realm: 'master', email: 'user@example.com');

        $client->queueResult('getUsers', ['result']);

        self::assertSame(['result'], $client->getUsers($dto));
        self::assertSame(
            [
                [
                    'method' => 'getUsers',
                    'args' => [$dto],
                ],
            ],
            $client->getCalls(),
        );
    }

    public function testMissingQueueThrows(): void
    {
        $this->expectException(LogicException::class);

        $client = new TestKeycloakHttpClient();
        $client->getAvailableRealms();
    }

    public function testQueuedThrowableIsRethrown(): void
    {
        $this->expectException(RuntimeException::class);

        $client = new TestKeycloakHttpClient();
        $client->queueResult('getRoles', new RuntimeException('boom'));
        $client->getRoles();
    }

    public function testCreateUserConsumesQueue(): void
    {
        $client = new TestKeycloakHttpClient();
        $profile = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: false,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );
        $credential = new KeycloakCredential(
            type: KeycloakCredentialType::password(),
            credentialData: '{}',
            secretData: '{}',
            temporary: true,
        );
        $createUserDto = new CreateUserDto(profile: $profile, credentials: [$credential]);

        $client->queueResult('createUser', null);
        $client->createUser($createUserDto);

        self::assertSame(
            [
                [
                    'method' => 'createUser',
                    'args' => [$createUserDto],
                ],
            ],
            $client->getCalls(),
        );
    }
}
