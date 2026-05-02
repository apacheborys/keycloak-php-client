<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use TypeError;

final class DeleteUserDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $dto = new DeleteUserDto(
            realm: 'master',
            userId: Uuid::fromString('92a372d5-c338-4e77-a1b3-08771241036e'),
            localUserId: 'local-user-42',
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame('92a372d5-c338-4e77-a1b3-08771241036e', $dto->getUserId()?->toString());
        self::assertSame('local-user-42', $dto->getLocalUserId());
    }

    public function testAllowsMissingKeycloakUserIdForMapperGeneratedDto(): void
    {
        $dto = new DeleteUserDto(
            realm: 'master',
            localUserId: 'local-user-42',
        );

        self::assertNull($dto->getUserId());
        self::assertSame('local-user-42', $dto->getLocalUserId());
    }

    public function testInvalidUserIdTypeThrows(): void
    {
        $this->expectException(TypeError::class);

        new DeleteUserDto(
            realm: 'master',
            userId: 'not-a-uuid',
        );
    }
}
