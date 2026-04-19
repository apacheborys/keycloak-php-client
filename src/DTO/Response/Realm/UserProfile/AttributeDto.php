<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorType;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorsDto;
use Assert\Assert;

final readonly class AttributeDto
{
    /**
     * @param array{view: list<string>, edit: list<string>} $permissions
     * @param array<string, mixed> $annotations
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private string $name,
        private ?string $displayName = null,
        private array $permissions = ['view' => [], 'edit' => []],
        private bool $multivalued = false,
        private array $annotations = [],
        private ?AttributeValidatorsDto $validators = null,
        private array $extra = [],
    ) {
        Assert::that($this->name)->string()->notBlank();

        if ($this->displayName !== null) {
            Assert::that($this->displayName)->string()->notBlank();
        }

        Assert::that($this->permissions)->keyExists('view');
        Assert::that($this->permissions)->keyExists('edit');
        Assert::that($this->permissions['view'])->isArray();
        Assert::that($this->permissions['edit'])->isArray();

        foreach ($this->permissions['view'] as $permission) {
            Assert::that($permission)->string()->notBlank();
        }

        foreach ($this->permissions['edit'] as $permission) {
            Assert::that($permission)->string()->notBlank();
        }

        foreach ($this->annotations as $key => $value) {
            Assert::that($key)->string()->notBlank();
            $_ = $value;
        }

        foreach ($this->extra as $key => $value) {
            Assert::that($key)->string()->notBlank();
            $_ = $value;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    /**
     * @return array{view: list<string>, edit: list<string>}
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function isMultivalued(): bool
    {
        return $this->multivalued;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function getValidators(): ?AttributeValidatorsDto
    {
        return $this->validators;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getValidations(): array
    {
        if ($this->validators === null) {
            return [];
        }

        return $this->validators->toKeycloakArray();
    }

    public function hasValidator(AttributeValidatorType $type): bool
    {
        if ($this->validators === null) {
            return false;
        }

        return $this->validators->has($type);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->extra;
        $data['name'] = $this->name;
        $data['validations'] = $this->getValidations();
        $data['permissions'] = $this->permissions;
        $data['multivalued'] = $this->multivalued;
        $data['annotations'] = $this->annotations;

        if ($this->displayName !== null) {
            $data['displayName'] = $this->displayName;
        } else {
            unset($data['displayName']);
        }

        return $data;
    }

    public function withPreservedUnknownFieldsFrom(self $attribute): self
    {
        return new self(
            name: $this->name,
            displayName: $this->displayName,
            permissions: $this->permissions,
            multivalued: $this->multivalued,
            annotations: $this->annotations,
            validators: self::mergeValidators(
                current: $attribute->validators,
                updated: $this->validators,
            ),
            extra: array_replace($attribute->extra, $this->extra),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('name');
        Assert::that($data['name'])->string()->notBlank();

        /** @var string $name */
        $name = $data['name'];

        $validations = self::normalizeValidations(data: $data['validations'] ?? []);
        $permissions = self::normalizePermissions(data: $data['permissions'] ?? ['view' => [], 'edit' => []]);
        $annotations = self::normalizeAnnotations(data: $data['annotations'] ?? []);

        return new self(
            name: $name,
            displayName: is_string($data['displayName'] ?? null) ? $data['displayName'] : null,
            permissions: $permissions,
            multivalued: (bool) ($data['multivalued'] ?? false),
            annotations: $annotations,
            validators: $validations === [] ? null : AttributeValidatorsDto::fromKeycloakArray($validations),
            extra: self::extractExtra(data: $data),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeValidations(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $validations = [];
        foreach ($data as $validator => $options) {
            Assert::that($validator)->string()->notBlank();
            Assert::that($options)->isArray();

            /** @var string $validator */
            /** @var array<string, mixed> $options */
            $validations[$validator] = $options;
        }

        return $validations;
    }

    /**
     * @return array{view: list<string>, edit: list<string>}
     */
    private static function normalizePermissions(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $view = self::normalizePermissionList(data: $data['view'] ?? []);
        $edit = self::normalizePermissionList(data: $data['edit'] ?? []);

        return [
            'view' => $view,
            'edit' => $edit,
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizePermissionList(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int, mixed> $data */

        $permissions = [];
        foreach ($data as $permission) {
            Assert::that($permission)->string()->notBlank();
            /** @var string $permission */
            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeAnnotations(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $annotations = [];
        foreach ($data as $key => $value) {
            Assert::that($key)->string()->notBlank();
            /** @var string $key */
            $annotations[$key] = $value;
        }

        return $annotations;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractExtra(array $data): array
    {
        unset(
            $data['name'],
            $data['displayName'],
            $data['validations'],
            $data['permissions'],
            $data['multivalued'],
            $data['annotations'],
        );

        return $data;
    }

    private static function mergeValidators(
        ?AttributeValidatorsDto $current,
        ?AttributeValidatorsDto $updated,
    ): ?AttributeValidatorsDto {
        if ($current === null) {
            return $updated;
        }

        if ($updated === null) {
            return $current;
        }

        return AttributeValidatorsDto::fromKeycloakArray(
            array_replace($current->toKeycloakArray(), $updated->toKeycloakArray()),
        );
    }
}
