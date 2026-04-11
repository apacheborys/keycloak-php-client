<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientScopesProtocolMapperDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = ClientScopesProtocolMapperDto::fromArray(
            [
                'id' => 'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
                'name' => 'External user id attribute',
                'protocol' => 'openid-connect',
                'protocolMapper' => 'oidc-usermodel-attribute-mapper',
                'consentRequired' => false,
                'config' => [
                    'user.attribute' => 'external-user-id',
                    'claim.name' => 'external_user_id',
                    'jsonType.label' => 'String',
                ],
            ],
        );

        self::assertNotNull($dto->getId());
        self::assertSame('d4e57d40-32a6-4c24-9ae1-b704d5ed882f', $dto->getId()?->toString());
        self::assertSame('External user id attribute', $dto->getName());
        self::assertSame('openid-connect', $dto->getProtocol());
        self::assertSame('oidc-usermodel-attribute-mapper', $dto->getProtocolMapper());
        self::assertFalse($dto->isConsentRequired());
        self::assertSame('external_user_id', $dto->getConfig()['claim.name'] ?? null);

        self::assertSame(
            [
                'name' => 'External user id attribute',
                'protocol' => 'openid-connect',
                'protocolMapper' => 'oidc-usermodel-attribute-mapper',
                'consentRequired' => false,
                'config' => [
                    'user.attribute' => 'external-user-id',
                    'claim.name' => 'external_user_id',
                    'jsonType.label' => 'String',
                ],
                'id' => 'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClientScopesProtocolMapperDto::fromArray(
            [
                'id' => 'not-uuid',
                'name' => 'mapper',
                'protocol' => 'openid-connect',
                'protocolMapper' => 'oidc-usermodel-attribute-mapper',
            ],
        );
    }
}

