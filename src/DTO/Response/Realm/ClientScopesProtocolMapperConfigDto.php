<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm;

use Assert\Assert;

final readonly class ClientScopesProtocolMapperConfigDto
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(private array $values = [])
    {
        foreach ($this->values as $key => $value) {
            Assert::that($key)->string()->notBlank();
            Assert::that($value)->string();
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            Assert::that($key)->string()->notBlank();
            Assert::that($value)->scalar();
            /** @var string $key */

            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
                continue;
            }

            /** @var scalar $value */
            $normalized[$key] = (string) $value;
        }

        return new self(values: $normalized);
    }
}
