<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators;

use Assert\Assert;

final readonly class AttributeValidatorDto implements AttributeValidatorInterface
{
    private string $type;

    /**
     * @param array<string, mixed> $config
     * @param AttributeValidatorType|string $type
     */
    public function __construct(
        AttributeValidatorType|string $type,
        private array $config = [],
    ) {
        $this->type = $type instanceof AttributeValidatorType ? $type->value : $type;

        Assert::that($this->type)->string()->notBlank();
        foreach ($this->config as $key => $_value) {
            Assert::that($key)->string()->notBlank();
        }
    }

    #[\Override]
    public function getType(): string
    {
        return $this->type;
    }

    public function getKnownType(): ?AttributeValidatorType
    {
        return AttributeValidatorType::tryFrom($this->type);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array{type: string, config: array<string, mixed>}
     */
    #[\Override]
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'config' => $this->config,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromTypeAndConfig(string $type, array $config): self
    {
        return new self(
            type: $type,
            config: $config,
        );
    }
}
