<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm;

use Assert\Assert;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class ClientScopesProtocolMapperDto
{
    private ClientScopesProtocolMapperConfigDto $config;

    /**
     * @param ClientScopesProtocolMapperConfigDto|array<string, string> $config
     */
    public function __construct(
        private string $name,
        private string $protocol,
        private string $protocolMapper,
        private bool $consentRequired = false,
        ClientScopesProtocolMapperConfigDto|array $config = [],
        private ?UuidInterface $id = null,
    ) {
        Assert::that($this->name)->string()->notBlank();
        Assert::that($this->protocol)->string()->notBlank();
        Assert::that($this->protocolMapper)->string()->notBlank();
        $this->config = $config instanceof ClientScopesProtocolMapperConfigDto
            ? $config
            : ClientScopesProtocolMapperConfigDto::fromArray($config);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getProtocolMapper(): string
    {
        return $this->protocolMapper;
    }

    public function isConsentRequired(): bool
    {
        return $this->consentRequired;
    }

    public function getConfig(): ClientScopesProtocolMapperConfigDto
    {
        return $this->config;
    }

    /**
     * @return array<string, string>
     */
    public function getConfigAsArray(): array
    {
        return $this->config->toArray();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * @return array{
     *     id?: string,
     *     name: string,
     *     protocol: string,
     *     protocolMapper: string,
     *     consentRequired: bool,
     *     config: array<string, string>
     * }
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'protocol' => $this->protocol,
            'protocolMapper' => $this->protocolMapper,
            'consentRequired' => $this->consentRequired,
            'config' => $this->config->toArray(),
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id->toString();
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
        Assert::that($data)->keyExists('protocolMapper');
        Assert::that($data['protocolMapper'])->string()->notBlank();

        /** @var array{
         *     id?: mixed,
         *     name: string,
         *     protocol: string,
         *     protocolMapper: string,
         *     consentRequired?: mixed,
         *     config?: mixed
         * } $data
         */

        if (array_key_exists('id', $data) && $data['id'] !== null) {
            Assert::that($data['id'])->string()->notBlank();
            /** @var string $id */
            $id = $data['id'];
            Assert::that(Uuid::isValid($id))->true();
        }

        return new self(
            name: $data['name'],
            protocol: $data['protocol'],
            protocolMapper: $data['protocolMapper'],
            consentRequired: (bool) ($data['consentRequired'] ?? false),
            config: ClientScopesProtocolMapperConfigDto::fromArray(
                data: self::normalizeArray(data: $data['config'] ?? []),
            ),
            id: is_string($data['id'] ?? null) ? Uuid::fromString($data['id']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeArray(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $result = [];
        foreach ($data as $key => $value) {
            Assert::that($key)->string()->notBlank();
            /** @var string $key */
            $result[$key] = $value;
        }

        return $result;
    }
}
