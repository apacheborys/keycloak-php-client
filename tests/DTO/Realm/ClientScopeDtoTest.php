<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm;

use Apacheborys\KeycloakPhpClient\DTO\Realm\ClientScopeDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientScopeDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = ClientScopeDto::fromArray(
            [
                'id' => '39c0fcbc-db18-4236-8cae-2c074d730f4b',
                'name' => 'backend-dedicated',
                'description' => 'Backend client scope',
                'protocol' => 'openid-connect',
                'attributes' => [
                    'include.in.token.scope' => 'true',
                    'display.on.consent.screen' => 'true',
                ],
                'protocolMappers' => [
                    [
                        'id' => 'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
                        'name' => 'External user id attribute',
                        'protocol' => 'openid-connect',
                        'protocolMapper' => 'oidc-usermodel-attribute-mapper',
                        'consentRequired' => false,
                        'config' => [
                            'user.attribute' => 'external-user-id',
                            'claim.name' => 'external_user_id',
                        ],
                    ],
                ],
            ],
        );

        self::assertNotNull($dto->getId());
        self::assertSame('39c0fcbc-db18-4236-8cae-2c074d730f4b', $dto->getId()?->toString());
        self::assertSame('backend-dedicated', $dto->getName());
        self::assertSame('Backend client scope', $dto->getDescription());
        self::assertSame('openid-connect', $dto->getProtocol());
        self::assertSame('true', $dto->getAttributes()['include.in.token.scope'] ?? null);
        self::assertCount(1, $dto->getProtocolMappers());
        self::assertSame(
            'External user id attribute',
            $dto->getProtocolMappers()[0]->getName(),
        );

        $array = $dto->toArray();
        self::assertSame('39c0fcbc-db18-4236-8cae-2c074d730f4b', $array['id'] ?? null);
        self::assertSame('backend-dedicated', $array['name'] ?? null);
        self::assertSame('Backend client scope', $array['description'] ?? null);
        self::assertSame('openid-connect', $array['protocol'] ?? null);
        self::assertSame('true', $array['attributes']['display.on.consent.screen'] ?? null);
        self::assertCount(1, $array['protocolMappers'] ?? []);
        self::assertSame(
            'd4e57d40-32a6-4c24-9ae1-b704d5ed882f',
            $array['protocolMappers'][0]['id'] ?? null,
        );
    }

    public function testInvalidProtocolThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientScopeDto(
            name: 'backend-dedicated',
            protocol: '',
        );
    }
}

