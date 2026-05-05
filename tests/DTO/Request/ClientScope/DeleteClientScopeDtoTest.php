<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\ClientScope;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DeleteClientScopeDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $id = Uuid::fromString('f480fece-9dc0-41e6-9a6a-ac25137d800e');
        $dto = new DeleteClientScopeDto(
            realm: 'master',
            clientScopeId: $id,
            removeFromRealmDefaultAssignmentsBeforeDelete: false,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($id->toString(), $dto->getClientScopeId()->toString());
        self::assertFalse($dto->shouldRemoveFromRealmDefaultAssignmentsBeforeDelete());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeleteClientScopeDto(
            realm: '',
            clientScopeId: Uuid::fromString('f480fece-9dc0-41e6-9a6a-ac25137d800e'),
        );
    }
}
