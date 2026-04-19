<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\ValueObject\ClientScopeRealmAssignmentType;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateClientScopeDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $scope = new ClientScopeDto(
            name: 'test-client-scope',
            protocol: 'openid-connect',
        );
        $dto = new CreateClientScopeDto(
            realm: 'master',
            clientScope: $scope,
            realmAssignmentType: ClientScopeRealmAssignmentType::DEFAULT,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($scope, $dto->getClientScope());
        self::assertSame(ClientScopeRealmAssignmentType::DEFAULT, $dto->getRealmAssignmentType());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreateClientScopeDto(
            realm: '',
            clientScope: new ClientScopeDto(
                name: 'test-client-scope',
                protocol: 'openid-connect',
            ),
        );
    }
}
