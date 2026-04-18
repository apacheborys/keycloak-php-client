<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile;

use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorType;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators\AttributeValidatorsDto;
use Apacheborys\KeycloakPhpClient\ValueObject\AttributePermission;
use Assert\Assert;

final readonly class AttributeDto
{
    /**
     * @param array{view: list<string>, edit: list<string>} $permissions
     * @param array<string, string> $annotations
     */
    public function __construct(
        private string $name,
        private ?string $displayName = null,
        private array $permissions = ['view' => [], 'edit' => []],
        private bool $multivalued = false,
        private array $annotations = [],
        private ?AttributeValidatorsDto $validators = null,
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
            Assert::that(AttributePermission::tryFrom($permission))->notNull();
        }

        foreach ($this->permissions['edit'] as $permission) {
            Assert::that($permission)->string()->notBlank();
            Assert::that(AttributePermission::tryFrom($permission))->notNull();
        }

        foreach ($this->annotations as $key => $value) {
            Assert::that($key)->string()->notBlank();
            Assert::that($value)->string();
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
     * @return array<string, string>
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
     * @return array{
     *     name: string,
     *     displayName?: string,
     *     validations: array<string, array<string, mixed>>,
     *     permissions: array{view: list<string>, edit: list<string>},
     *     multivalued: bool,
     *     annotations: array<string, string>
     * }
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'validations' => $this->getValidations(),
            'permissions' => $this->permissions,
            'multivalued' => $this->multivalued,
            'annotations' => $this->annotations,
        ];

        if ($this->displayName !== null) {
            $data['displayName'] = $this->displayName;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('name');
        Assert::that($data['name'])->string()->notBlank();

        /** @var array{
         *     name: string,
         *     displayName?: mixed,
         *     validations?: mixed,
         *     permissions?: mixed,
         *     multivalued?: mixed,
         *     annotations?: mixed
         * } $data
         */

        $validations = self::normalizeValidations(data: $data['validations'] ?? []);
        $permissions = self::normalizePermissions(data: $data['permissions'] ?? ['view' => [], 'edit' => []]);
        $annotations = self::normalizeAnnotations(data: $data['annotations'] ?? []);

        return new self(
            name: $data['name'],
            displayName: is_string($data['displayName'] ?? null) ? $data['displayName'] : null,
            permissions: $permissions,
            multivalued: (bool) ($data['multivalued'] ?? false),
            annotations: $annotations,
            validators: AttributeValidatorsDto::fromKeycloakArray($validations),
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
            Assert::that(AttributePermission::tryFrom($permission))->notNull();
            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeAnnotations(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $annotations = [];
        foreach ($data as $key => $value) {
            Assert::that($key)->string()->notBlank();
            Assert::that($value)->scalar();

            /** @var string $key */
            /** @var scalar $value */
            $annotations[$key] = (string) $value;
        }

        return $annotations;
    }
}
