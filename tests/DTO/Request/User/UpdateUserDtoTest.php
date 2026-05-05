<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\User;

use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\UpdateUserProfileDto;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use TypeError;

final class UpdateUserDtoTest extends TestCase
{
    public function testGettersAndPayload(): void
    {
        $dto = new UpdateUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            profile: new UpdateUserProfileDto(
                username: 'oleg@example.com',
                email: 'oleg.new@example.com',
                firstName: 'Oleg',
                emailVerified: true,
                attributes: [
                    'external-user-id' => 'external-id-4',
                ],
            ),
            localUserId: 42,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $dto->getUserId()?->toString());
        self::assertSame(42, $dto->getLocalUserId());
        self::assertSame('oleg@example.com', $dto->getProfile()->getUsername());
        self::assertSame('oleg.new@example.com', $dto->getProfile()->getEmail());
        self::assertSame(
            [
                'username' => 'oleg@example.com',
                'email' => 'oleg.new@example.com',
                'emailVerified' => true,
                'firstName' => 'Oleg',
                'attributes' => [
                    'external-user-id' => ['external-id-4'],
                ],
            ],
            $dto->toArray(),
        );
        self::assertArrayNotHasKey('localUserId', $dto->toArray());
    }

    public function testAcceptsUuidLocalUserId(): void
    {
        $localUserId = Uuid::fromString('a912d2b1-0b23-4c50-89f4-41c52f286cb8');

        $dto = new UpdateUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'user@example.com',
            ),
            localUserId: $localUserId,
        );

        self::assertSame($localUserId, $dto->getLocalUserId());
    }

    public function testAllowsMissingKeycloakUserIdForMapperGeneratedDto(): void
    {
        $dto = new UpdateUserDto(
            realm: 'master',
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'user@example.com',
            ),
            localUserId: 'local-user-1',
        );

        self::assertNull($dto->getUserId());
        self::assertSame('local-user-1', $dto->getLocalUserId());
    }

    public function testInvalidUserIdTypeThrows(): void
    {
        $this->expectException(TypeError::class);

        new UpdateUserDto(
            realm: 'master',
            userId: 'not-a-uuid',
            profile: new UpdateUserProfileDto(
                username: 'user@example.com',
                email: 'user@example.com',
            ),
        );
    }
}
