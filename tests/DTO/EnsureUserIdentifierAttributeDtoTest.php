<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\EnsureUserIdentifierAttributeDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnsureUserIdentifierAttributeDtoTest extends TestCase
{
    public function testGettersAndDefaults(): void
    {
        $dto = new EnsureUserIdentifierAttributeDto(
            attributeName: 'external-user-id',
            displayName: 'External user id',
            createIfMissing: true,
            exposeInJwt: true,
        );

        self::assertSame('external-user-id', $dto->getAttributeName());
        self::assertSame('External user id', $dto->getDisplayName());
        self::assertTrue($dto->shouldCreateIfMissing());
        self::assertTrue($dto->shouldExposeInJwt());
        self::assertSame('profile', $dto->getClientScopeName());
        self::assertSame('external_user_id', $dto->getJwtClaimName());
        self::assertSame('External user id attribute', $dto->getProtocolMapperName());
    }

    public function testExplicitClaimAndProtocolMapperNames(): void
    {
        $dto = new EnsureUserIdentifierAttributeDto(
            attributeName: 'external-user-id',
            displayName: 'External user id',
            createIfMissing: false,
            exposeInJwt: true,
            clientScopeName: 'backend-dedicated',
            jwtClaimName: 'external_user_id_custom',
            protocolMapperName: 'External user id mapper',
        );

        self::assertSame('backend-dedicated', $dto->getClientScopeName());
        self::assertSame('external_user_id_custom', $dto->getJwtClaimName());
        self::assertSame('External user id mapper', $dto->getProtocolMapperName());
    }

    public function testBlankAttributeNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnsureUserIdentifierAttributeDto(
            attributeName: '',
            displayName: 'External user id',
        );
    }
}
