<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UpdateClientScopeProtocolMapperDtoTest extends TestCase
{
    public function testGetters(): void
    {
        $clientScopeId = Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b');
        $mapperId = Uuid::fromString('3b1caa7b-dad7-4f43-9127-15969f303fe8');
        $mapper = new ClientScopesProtocolMapperDto(
            id: $mapperId,
            name: 'External user id attribute',
            protocol: 'openid-connect',
            protocolMapper: 'oidc-usermodel-attribute-mapper',
            config: [
                'claim.name' => 'external_user_id_test',
                'user.attribute' => 'external-user-id',
            ],
        );

        $dto = new UpdateClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: $clientScopeId,
            protocolMapperId: $mapperId,
            protocolMapper: $mapper,
        );

        self::assertSame('master', $dto->getRealm());
        self::assertSame($clientScopeId->toString(), $dto->getClientScopeId()->toString());
        self::assertSame($mapperId->toString(), $dto->getProtocolMapperId()->toString());
        self::assertSame($mapper, $dto->getProtocolMapper());
    }

    public function testPayloadIdMismatchThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UpdateClientScopeProtocolMapperDto(
            realm: 'master',
            clientScopeId: Uuid::fromString('39c0fcbc-db18-4236-8cae-2c074d730f4b'),
            protocolMapperId: Uuid::fromString('3b1caa7b-dad7-4f43-9127-15969f303fe8'),
            protocolMapper: new ClientScopesProtocolMapperDto(
                id: Uuid::fromString('d4e57d40-32a6-4c24-9ae1-b704d5ed882f'),
                name: 'Mapper',
                protocol: 'openid-connect',
                protocolMapper: 'oidc-usermodel-attribute-mapper',
            ),
        );
    }
}
