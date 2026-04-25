<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile;

use Assert\Assert;

final readonly class AttributeRequiredDto
{
    /**
     * @param list<string> $roles
     * @param list<string> $scopes
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private array $roles = [],
        private array $scopes = [],
        private array $extra = [],
    ) {
        foreach ($this->roles as $role) {
            Assert::that($role)->string()->notBlank();
        }

        foreach ($this->scopes as $scope) {
            Assert::that($scope)->string()->notBlank();
        }

        foreach ($this->extra as $key => $value) {
            Assert::that($key)->string()->notBlank();
            $_ = $value;
        }
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function isAlways(): bool
    {
        return $this->roles === [] && $this->scopes === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->extra;

        if ($this->roles !== []) {
            $data['roles'] = $this->roles;
        } else {
            unset($data['roles']);
        }

        if ($this->scopes !== []) {
            $data['scopes'] = $this->scopes;
        } else {
            unset($data['scopes']);
        }

        return $data;
    }

    public function withPreservedUnknownFieldsFrom(self $required): self
    {
        return new self(
            roles: $this->roles,
            scopes: $this->scopes,
            extra: array_replace($required->extra, $this->extra),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            roles: self::normalizeStringList(data: $data['roles'] ?? []),
            scopes: self::normalizeStringList(data: $data['scopes'] ?? []),
            extra: self::extractExtra(data: $data),
        );
    }

    /**
     * @return list<string>
     */
    private static function normalizeStringList(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int, mixed> $data */

        $values = [];
        foreach ($data as $value) {
            Assert::that($value)->string()->notBlank();
            /** @var string $value */
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractExtra(array $data): array
    {
        unset($data['roles'], $data['scopes']);

        return $data;
    }
}
