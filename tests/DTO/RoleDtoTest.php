<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoleDtoTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $dto = RoleDto::fromArray(
            [
                'id' => 'd813c30d-66ee-482d-a33e-4b2c96fc6361',
                'name' => 'default-roles-master',
                'description' => '${role_default-roles}',
                'composite' => true,
                'clientRole' => false,
                'containerId' => '992b5dcf-1cdc-4b69-8fe2-0beaec437b17',
            ]
        );

        self::assertSame('default-roles-master', $dto->getName());
        self::assertSame('d813c30d-66ee-482d-a33e-4b2c96fc6361', $dto->getId());
        self::assertSame('${role_default-roles}', $dto->getDescription());
        self::assertTrue($dto->isComposite());
        self::assertFalse($dto->isClientRole());
        self::assertSame('992b5dcf-1cdc-4b69-8fe2-0beaec437b17', $dto->getContainerId());
        self::assertSame(
            [
                'name' => 'default-roles-master',
                'composite' => true,
                'clientRole' => false,
                'id' => 'd813c30d-66ee-482d-a33e-4b2c96fc6361',
                'description' => '${role_default-roles}',
                'containerId' => '992b5dcf-1cdc-4b69-8fe2-0beaec437b17',
            ],
            $dto->toArray(),
        );
        self::assertSame(
            [
                'name' => 'default-roles-master',
                'composite' => true,
                'clientRole' => false,
                'description' => '${role_default-roles}',
            ],
            $dto->toCreatePayload(),
        );
    }

    public function testInvalidNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RoleDto(name: '');
    }
}
