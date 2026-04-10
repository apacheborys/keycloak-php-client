<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\Validators;

use Assert\Assert;

final readonly class AttributeValidatorDto implements AttributeValidatorInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private AttributeValidatorType $type,
        private array $config = [],
    ) {
        foreach ($this->config as $key => $_value) {
            Assert::that($key)->string()->notBlank();
        }
    }

    public function getType(): AttributeValidatorType
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array{type: string, config: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'config' => $this->config,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromTypeAndConfig(string $type, array $config): self
    {
        $enumType = AttributeValidatorType::tryFrom($type);
        if (!$enumType instanceof AttributeValidatorType) {
            throw new \InvalidArgumentException(sprintf('Unsupported Keycloak attribute validator: %s', $type));
        }

        return new self(
            type: $enumType,
            config: $config,
        );
    }
}
