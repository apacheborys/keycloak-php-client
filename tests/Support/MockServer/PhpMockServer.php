<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\MockServer;

use RuntimeException;

final class PhpMockServer
{
    /**
     * @var resource|null
     */
    private $process = null;

    private string $host = '127.0.0.1';
    private int $port;
    private string $scenarioFile;
    private string $stateFile;
    private string $logFile;

    public function __construct()
    {
        $this->port = $this->allocatePort();
        $tmpDir = sys_get_temp_dir() . '/keycloak-php-client-mock-' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create temporary directory for mock server.');
        }

        $this->scenarioFile = $tmpDir . '/scenario.json';
        $this->stateFile = $tmpDir . '/state.json';
        $this->logFile = $tmpDir . '/requests.log';

        file_put_contents($this->scenarioFile, '{}');
        file_put_contents($this->stateFile, '');
        file_put_contents($this->logFile, '');

        $routerScript = __DIR__ . '/router.php';
        $command = sprintf(
            '%s -S %s:%d %s',
            escapeshellarg(PHP_BINARY),
            $this->host,
            $this->port,
            escapeshellarg($routerScript),
        );

        $environment = array_merge(
            $_ENV,
            [
                'MOCK_SERVER_SCENARIO_FILE' => $this->scenarioFile,
                'MOCK_SERVER_STATE_FILE' => $this->stateFile,
                'MOCK_SERVER_LOG_FILE' => $this->logFile,
            ],
        );

        $this->process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            $environment,
        );

        if (!is_resource($this->process)) {
            throw new RuntimeException('Unable to start PHP built-in mock server.');
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->waitUntilReady();
    }

    public function getBaseUrl(): string
    {
        return sprintf('http://%s:%d', $this->host, $this->port);
    }

    /**
     * @param array<string, list<array{status: int, headers?: array<string, string>, body?: string}>> $scenario
     */
    public function setScenario(array $scenario): void
    {
        /** @var string $encoded */
        $encoded = json_encode($scenario, JSON_THROW_ON_ERROR);
        file_put_contents($this->scenarioFile, $encoded);
        file_put_contents($this->stateFile, '');
        file_put_contents($this->logFile, '');
    }

    /**
     * @return list<array{
     *     method: string,
     *     uri: string,
     *     headers: array<string, string>,
     *     body: string
     * }>
     */
    public function getRequests(): array
    {
        $content = @file_get_contents($this->logFile);
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $result = [];
        foreach (explode("\n", trim($content)) as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                /** @var array{
                 *     method: string,
                 *     uri: string,
                 *     headers: array<string, string>,
                 *     body: string
                 * } $decoded
                 */
                $result[] = $decoded;
            }
        }

        return $result;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function allocatePort(): int
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($server)) {
            throw new RuntimeException('Unable to allocate local port: ' . $errorMessage . ' (' . $errorCode . ')');
        }

        $name = stream_socket_get_name($server, false);
        fclose($server);

        if (!is_string($name) || !str_contains($name, ':')) {
            throw new RuntimeException('Unable to detect allocated local port.');
        }

        $parts = explode(':', $name);
        $port = (int) end($parts);
        if ($port <= 0) {
            throw new RuntimeException('Allocated port is invalid.');
        }

        return $port;
    }

    private function waitUntilReady(): void
    {
        $maxAttempts = 100;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $connection = @fsockopen($this->host, $this->port);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(20_000);
        }

        throw new RuntimeException('Mock server failed to start in time.');
    }
}
