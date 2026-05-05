<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\ClientScope;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UpdateClientScopeDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $id = Uuid::fromString('f480fece-9dc0-41e6-9a6a-ac25137d800e');
        $scope = new ClientScopeDto(
            id: $id,
            name: 'test-client-scope',
            protocol: 'openid-connect',
        );

        $dto = new UpdateClientScopeDto(
            realm: 'master',
            clientScopeId: $id,
            clientScope: $scope,
            realmAssignmentType: ClientScopeRealmAssignmentType::OPTIONAL,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($id->toString(), $dto->getClientScopeId()->toString());
        self::assertSame($scope, $dto->getClientScope());
        self::assertSame(ClientScopeRealmAssignmentType::OPTIONAL, $dto->getRealmAssignmentType());
    }

    public function testPayloadIdMismatchThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateClientScopeDto(
            realm: 'master',
            clientScopeId: Uuid::fromString('f480fece-9dc0-41e6-9a6a-ac25137d800e'),
            clientScope: new ClientScopeDto(
                id: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
                name: 'test-client-scope',
                protocol: 'openid-connect',
            ),
        );
    }
}
