<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteClientScopeProtocolMapperDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DeleteClientScopeProtocolMapperDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $clientScopeId = Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b');
        $mapperId = Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f');
        $dto = new DeleteClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: $clientScopeId,
            protocolMapperId: $mapperId,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($clientScopeId->toString(), $dto->getClientScopeId()->toString());
        self::assertSame($mapperId->toString(), $dto->getProtocolMapperId()->toString());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeleteClientScopeProtocolMapperDto(
            realm: '',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            protocolMapperId: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
        );
    }
}
