<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO;

use Assert\Assert;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class RoleDto
{
    public function __construct(
        private string $name,
        private ?UuidInterface $id = null,
        private ?string $description = null,
        private bool $composite = false,
        private bool $clientRole = false,
        private ?UuidInterface $containerId = null,
    ) {
        Assert::that($this->name)->string()->notBlank();

        if ($this->description !== null) {
            Assert::that($this->description)->string();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isComposite(): bool
    {
        return $this->composite;
    }

    public function isClientRole(): bool
    {
        return $this->clientRole;
    }

    public function getContainerId(): ?UuidInterface
    {
        return $this->containerId;
    }

    /**
     * @return array{
     *     id?: string,
     *     name: string,
     *     description?: string,
     *     composite: bool,
     *     clientRole: bool,
     *     containerId?: string
     * }
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'composite' => $this->composite,
            'clientRole' => $this->clientRole,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id->toString();
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->containerId !== null) {
            $data['containerId'] = $this->containerId->toString();
        }

        return $data;
    }

    /**
     * @return array{
     *     name: string,
     *     description?: string,
     *     composite: bool,
     *     clientRole: bool
     * }
     */
    public function toCreatePayload(): array
    {
        $payload = [
            'name' => $this->name,
            'composite' => $this->composite,
            'clientRole' => $this->clientRole,
        ];

        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        return $payload;
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
         *     id?: mixed,
         *     description?: mixed,
         *     composite?: mixed,
         *     clientRole?: mixed,
         *     containerId?: mixed
         * } $data
         */

        if (array_key_exists('id', $data) && $data['id'] !== null) {
            Assert::that($data['id'])->string()->notBlank();
            /** @var string $id */
            $id = $data['id'];
            Assert::that(Uuid::isValid($id))->true();
        }

        if (array_key_exists('description', $data) && $data['description'] !== null) {
            Assert::that($data['description'])->string();
        }

        if (array_key_exists('composite', $data) && $data['composite'] !== null) {
            Assert::that($data['composite'])->boolean();
        }

        if (array_key_exists('clientRole', $data) && $data['clientRole'] !== null) {
            Assert::that($data['clientRole'])->boolean();
        }

        if (array_key_exists('containerId', $data) && $data['containerId'] !== null) {
            Assert::that($data['containerId'])->string()->notBlank();
            /** @var string $containerId */
            $containerId = $data['containerId'];
            Assert::that(Uuid::isValid($containerId))->true();
        }

        return new self(
            name: $data['name'],
            id: is_string($data['id'] ?? null) ? Uuid::fromString($data['id']) : null,
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            composite: (bool) ($data['composite'] ?? false),
            clientRole: (bool) ($data['clientRole'] ?? false),
            containerId: is_string($data['containerId'] ?? null) ? Uuid::fromString($data['containerId']) : null,
        );
    }
}
