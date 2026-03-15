<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class SimpleStreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return new SimpleStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $contents = @file_get_contents($filename);
        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read file: ' . $filename);
        }

        return new SimpleStream($contents);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Expected a valid stream resource.');
        }

        $contents = stream_get_contents($resource);
        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read from resource.');
        }

        return new SimpleStream($contents);
    }
}
