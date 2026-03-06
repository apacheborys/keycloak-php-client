<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class NativePsr18Client implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $headerLines = [];
            foreach ($request->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $headerLines[] = $name . ': ' . $value;
                }
            }

            $context = stream_context_create(
                [
                    'http' => [
                        'method' => $request->getMethod(),
                        'header' => implode("\r\n", $headerLines),
                        'content' => (string) $request->getBody(),
                        'ignore_errors' => true,
                    ],
                ]
            );

            $body = @file_get_contents((string) $request->getUri(), false, $context);
            if ($body === false) {
                throw new RuntimeException('HTTP request failed for URI: ' . (string) $request->getUri());
            }

            /**
             * @var list<string> $http_response_header
             */
            $responseHeaders = $http_response_header ?? [];
            [$statusCode, $headers] = $this->parseResponseHeaders($responseHeaders);

            return new SimpleResponse(
                statusCode: $statusCode,
                headers: $headers,
                body: new SimpleStream($body),
            );
        } catch (Throwable $e) {
            throw new NativePsr18ClientException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param list<string> $responseHeaders
     * @return array{0: int, 1: array<string, list<string>>}
     */
    private function parseResponseHeaders(array $responseHeaders): array
    {
        if ($responseHeaders === []) {
            throw new RuntimeException('No response headers were received.');
        }

        $statusLine = $responseHeaders[0];
        if (!preg_match('/^HTTP\/\d+\.\d+\s+(\d{3})/', $statusLine, $matches)) {
            throw new RuntimeException('Unable to parse status line: ' . $statusLine);
        }

        $statusCode = (int) $matches[1];
        $headers = [];
        foreach (array_slice($responseHeaders, 1) as $headerLine) {
            if ($headerLine === '' || !str_contains($headerLine, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $headerLine, 2);
            $name = trim($name);
            $value = trim($value);
            $headers[$name][] = $value;
        }

        /** @var array<string, list<string>> $headers */

        return [$statusCode, $headers];
    }
}
