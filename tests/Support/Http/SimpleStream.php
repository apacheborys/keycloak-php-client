<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class SimpleStream implements StreamInterface
{
    private int $position = 0;
    private bool $closed = false;

    public function __construct(private string $contents = '')
    {
    }

    public function __toString(): string
    {
        return $this->contents;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->contents = '';
        $this->position = 0;
    }

    public function detach()
    {
        $this->close();

        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->contents);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->closed) {
            throw new RuntimeException('Stream is closed.');
        }

        $length = strlen($this->contents);
        $target = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $length + $offset,
            default => throw new RuntimeException('Invalid whence value.'),
        };

        if ($target < 0) {
            throw new RuntimeException('Negative seek position is not allowed.');
        }

        $this->position = $target;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        if ($this->closed) {
            throw new RuntimeException('Stream is closed.');
        }

        $left = substr($this->contents, 0, $this->position);
        $right = substr($this->contents, $this->position + strlen($string));
        $this->contents = $left . $string . $right;
        $written = strlen($string);
        $this->position += $written;

        return $written;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($this->closed) {
            throw new RuntimeException('Stream is closed.');
        }

        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        if ($this->closed) {
            throw new RuntimeException('Stream is closed.');
        }

        $chunk = substr($this->contents, $this->position);
        $this->position = strlen($this->contents);

        return $chunk;
    }

    public function getMetadata(?string $key = null): mixed
    {
        $metadata = [
            'seekable' => true,
            'readable' => true,
            'writable' => true,
        ];

        if ($key === null) {
            return $metadata;
        }

        return $metadata[$key] ?? null;
    }
}
