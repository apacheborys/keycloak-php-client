<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO\Realm;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperConfigDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientScopesProtocolMapperConfigDtoTest extends TestCase
{
    public function testFromArrayNormalizesScalarValuesToString(): void
    {
        $dto = ClientScopesProtocolMapperConfigDto::fromArray(
            [
                'claim.name' => 'external_user_id',
                'multivalued' => false,
                'number' => 42,
            ],
        );

        self::assertTrue($dto->has('claim.name'));
        self::assertSame('external_user_id', $dto->get('claim.name'));
        self::assertSame('42', $dto->get('number'));
        self::assertSame('false', $dto->get('multivalued'));
        self::assertSame(
            [
                'claim.name' => 'external_user_id',
                'multivalued' => 'false',
                'number' => '42',
            ],
            $dto->toArray(),
        );
    }

    public function testInvalidNestedValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClientScopesProtocolMapperConfigDto::fromArray(
            [
                'invalid' => ['nested'],
            ],
        );
    }
}
