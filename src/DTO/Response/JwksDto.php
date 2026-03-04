<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response;

use Assert\Assert;

final readonly class JwksDto
{
    /**
     * @param list<JwkDto> $keys
     */
    public function __construct(
        private array $keys,
    ) {
        foreach ($this->keys as $key) {
            Assert::that($key)->isInstanceOf(JwkDto::class);
        }
    }

    /**
     * @return list<JwkDto>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    public function findByKid(string $kid): ?JwkDto
    {
        foreach ($this->keys as $key) {
            if ($key->getKid() === $kid) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array{keys: list<array{
     *     kty: string,
     *     kid: string,
     *     use: string,
     *     alg: string,
     *     n: string,
     *     e: string,
     *     x5c: list<string>
     * }>}
     */
    public function toArray(): array
    {
        return [
            'keys' => array_map(
                static fn (JwkDto $key): array => $key->toArray(),
                $this->keys,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('keys');
        Assert::that($data['keys'])->isArray();

        /** @var array{keys: array<int, mixed>} $data */
        $keysData = $data['keys'];

        $keys = [];
        foreach ($keysData as $item) {
            Assert::that($item)->isArray();
            /** @var array<string, mixed> $item */
            $keys[] = JwkDto::fromArray($item);
        }

        return new self(keys: array_values($keys));
    }
}
