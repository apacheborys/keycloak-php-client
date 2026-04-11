<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm;

use Assert\Assert;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class ClientScopeDto
{
    /**
     * @param array<string, string> $attributes
     * @param list<ClientScopesProtocolMapperDto> $protocolMappers
     */
    public function __construct(
        private string $name,
        private string $protocol,
        private ?string $description = null,
        private array $attributes = [],
        private array $protocolMappers = [],
        private ?UuidInterface $id = null,
    ) {
        Assert::that($this->name)->string()->notBlank();
        Assert::that($this->protocol)->string()->notBlank();

        if ($this->description !== null) {
            Assert::that($this->description)->string();
        }

        foreach ($this->attributes as $key => $value) {
            Assert::that($key)->string()->notBlank();
            Assert::that($value)->string();
        }

        foreach ($this->protocolMappers as $mapper) {
            Assert::that($mapper)->isInstanceOf(ClientScopesProtocolMapperDto::class);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<ClientScopesProtocolMapperDto>
     */
    public function getProtocolMappers(): array
    {
        return $this->protocolMappers;
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * @return array{
     *     id?: string,
     *     name: string,
     *     description?: string,
     *     protocol: string,
     *     attributes: array<string, string>,
     *     protocolMappers: list<array{
     *         id?: string,
     *         name: string,
     *         protocol: string,
     *         protocolMapper: string,
     *         consentRequired: bool,
     *         config: array<string, string>
     *     }>
     * }
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'protocol' => $this->protocol,
            'attributes' => $this->attributes,
            'protocolMappers' => array_map(
                static fn (ClientScopesProtocolMapperDto $mapper): array => $mapper->toArray(),
                $this->protocolMappers,
            ),
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id->toString();
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
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
        Assert::that($data)->keyExists('protocol');
        Assert::that($data['protocol'])->string()->notBlank();

        /** @var array{
         *     id?: mixed,
         *     name: string,
         *     description?: mixed,
         *     protocol: string,
         *     attributes?: mixed,
         *     protocolMappers?: mixed
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

        return new self(
            name: $data['name'],
            protocol: $data['protocol'],
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            attributes: self::normalizeStringMap(data: $data['attributes'] ?? []),
            protocolMappers: self::normalizeProtocolMappers(data: $data['protocolMappers'] ?? []),
            id: is_string($data['id'] ?? null) ? Uuid::fromString($data['id']) : null,
        );
    }

    /**
     * @param mixed $data
     * @return array<string, string>
     */
    private static function normalizeStringMap(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $result = [];
        foreach ($data as $key => $value) {
            Assert::that($key)->string()->notBlank();
            Assert::that($value)->scalar();
            /** @var string $key */
            /** @var scalar $value */
            $result[$key] = (string) $value;
        }

        return $result;
    }

    /**
     * @param mixed $data
     * @return list<ClientScopesProtocolMapperDto>
     */
    private static function normalizeProtocolMappers(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int, mixed> $data */

        $result = [];
        foreach ($data as $mapper) {
            Assert::that($mapper)->isArray();
            /** @var array<string, mixed> $mapper */
            $result[] = ClientScopesProtocolMapperDto::fromArray($mapper);
        }

        return $result;
    }
}
