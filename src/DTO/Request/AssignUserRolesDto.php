<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class AssignUserRolesDto
{
    /**
     * @param list<RoleDto> $roles
     */
    public function __construct(
        private string $realm,
        private UuidInterface $userId,
        private array $roles,
    ) {
        Assert::that($this->realm)->notEmpty();

        foreach ($this->roles as $role) {
            Assert::that($role)->isInstanceOf(RoleDto::class);
        }
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getUserId(): UuidInterface
    {
        return $this->userId;
    }

    /**
     * @return list<RoleDto>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return list<array{
     *     id?: string,
     *     name: string,
     *     description?: string,
     *     composite: bool,
     *     clientRole: bool,
     *     containerId?: string
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (RoleDto $role): array => $role->toArray(),
            $this->roles,
        );
    }
}
