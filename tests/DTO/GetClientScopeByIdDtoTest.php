<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeByIdDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class GetClientScopeByIdDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $clientScopeId = Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b');
        $dto = new GetClientScopeByIdDto(
            realm: 'master',
            clientScopeId: $clientScopeId,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($clientScopeId->toString(), $dto->getClientScopeId()->toString());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GetClientScopeByIdDto(
            realm: '',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
        );
    }
}
