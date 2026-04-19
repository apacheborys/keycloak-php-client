<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators;

use Assert\Assert;

final readonly class AttributeValidatorsDto
{
    /**
     * @param list<AttributeValidatorInterface> $validators
     */
    public function __construct(private array $validators = [])
    {
        foreach ($this->validators as $validator) {
            Assert::that($validator)->isInstanceOf(AttributeValidatorInterface::class);
        }
    }

    /**
     * @return list<AttributeValidatorInterface>
     */
    public function getValidators(): array
    {
        return $this->validators;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toKeycloakArray(): array
    {
        $result = [];
        foreach ($this->validators as $validator) {
            $result[$validator->getType()->value] = $validator->getConfig();
        }

        return $result;
    }

    public function has(AttributeValidatorType $type): bool
    {
        foreach ($this->validators as $validator) {
            if ($validator->getType() === $type) {
                return true;
            }
        }

        return false;
    }

    public function get(AttributeValidatorType $type): ?AttributeValidatorInterface
    {
        foreach ($this->validators as $validator) {
            if ($validator->getType() === $type) {
                return $validator;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromKeycloakArray(array $data): self
    {
        $validators = [];
        foreach ($data as $validatorType => $config) {
            Assert::that($validatorType)->string()->notBlank();
            Assert::that($config)->isArray();

            /** @var string $validatorType */
            /** @var array<string, mixed> $config */
            $validators[] = AttributeValidatorDto::fromTypeAndConfig(
                type: $validatorType,
                config: $config,
            );
        }

        return new self(
            validators: array_values($validators),
        );
    }
}
