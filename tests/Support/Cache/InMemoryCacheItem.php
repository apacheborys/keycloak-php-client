<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

final class InMemoryCacheItem implements CacheItemInterface
{
    private bool $isHit = false;
    private mixed $value = null;
    private ?DateTimeImmutable $expiresAt = null;

    public function __construct(private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        if (!$this->isHit) {
            return false;
        }

        if ($this->expiresAt !== null && $this->expiresAt <= new DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration instanceof DateTimeImmutable
            ? $expiration
            : ($expiration !== null ? DateTimeImmutable::createFromInterface($expiration) : null);

        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        if ($time instanceof DateInterval) {
            $this->expiresAt = (new DateTimeImmutable())->add($time);

            return $this;
        }

        $this->expiresAt = (new DateTimeImmutable())->modify('+' . $time . ' seconds');

        return $this;
    }

    public function markAsHit(mixed $value, ?DateTimeImmutable $expiresAt = null): void
    {
        $this->value = $value;
        $this->isHit = true;
        $this->expiresAt = $expiresAt;
    }
}
