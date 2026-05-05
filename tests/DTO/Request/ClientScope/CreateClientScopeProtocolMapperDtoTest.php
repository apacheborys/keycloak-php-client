<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Request\ClientScope;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateClientScopeProtocolMapperDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $clientScopeId = Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b');
        $mapper = new ClientScopesProtocolMapperDto(
            name: 'External user id attribute',
            protocol: 'openid-connect',
            protocolMapper: 'oidc-usermodel-attribute-mapper',
            config: [
                'claim.name' => 'external_user_id',
                'user.attribute' => 'external-user-id',
            ],
        );

        $dto = new CreateClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: $clientScopeId,
            protocolMapper: $mapper,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($clientScopeId->toString(), $dto->getClientScopeId()->toString());
        self::assertSame($mapper, $dto->getProtocolMapper());
    }

    public function testEmptyRealmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreateClientScopeProtocolMapperDto(
            realm: '',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            protocolMapper: new ClientScopesProtocolMapperDto(
                name: 'Mapper',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
            ),
        );
    }
}
