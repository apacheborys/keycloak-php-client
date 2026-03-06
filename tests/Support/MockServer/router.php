<?php

declare(strict_types=1);

$scenarioFile = getenv('MOCK_SERVER_SCENARIO_FILE') ?: '';
$stateFile = getenv('MOCK_SERVER_STATE_FILE') ?: '';
$logFile = getenv('MOCK_SERVER_LOG_FILE') ?: '';

if ($scenarioFile === '' || $stateFile === '' || $logFile === '') {
    http_response_code(500);
    echo 'Mock server is not configured.';

    return;
}

$request = [
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    'uri' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    'headers' => collectHeaders(),
    'body' => (string) file_get_contents('php://input'),
];

file_put_contents(
    $logFile,
    json_encode($request, JSON_THROW_ON_ERROR) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

$response = popResponse(
    scenarioFile: $scenarioFile,
    stateFile: $stateFile,
    method: $request['method'],
    uri: $request['uri'],
);

http_response_code((int) $response['status']);
foreach ($response['headers'] as $headerName => $headerValue) {
    header($headerName . ': ' . $headerValue);
}

echo (string) $response['body'];

/**
 * @return array<string, string>
 */
function collectHeaders(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            $normalized = [];
            foreach ($headers as $name => $value) {
                $normalized[strtolower((string) $name)] = (string) $value;
            }

            return $normalized;
        }
    }

    $normalized = [];
    foreach ($_SERVER as $key => $value) {
        if (!str_starts_with($key, 'HTTP_')) {
            continue;
        }

        $name = strtolower(str_replace('_', '-', substr($key, 5)));
        $normalized[$name] = (string) $value;
    }

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $normalized['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
    }

    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $normalized['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
    }

    return $normalized;
}

/**
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function popResponse(string $scenarioFile, string $stateFile, string $method, string $uri): array
{
    $stateHandle = fopen($stateFile, 'c+');
    if (!is_resource($stateHandle)) {
        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'Unable to open state file.',
        ];
    }

    flock($stateHandle, LOCK_EX);
    $stateContent = stream_get_contents($stateHandle);
    $state = [];

    if (is_string($stateContent) && trim($stateContent) !== '') {
        $decodedState = json_decode($stateContent, true, flags: JSON_THROW_ON_ERROR);
        if (is_array($decodedState)) {
            $state = $decodedState;
        }
    } else {
        $scenarioContent = (string) file_get_contents($scenarioFile);
        if (trim($scenarioContent) !== '') {
            $decodedScenario = json_decode($scenarioContent, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($decodedScenario)) {
                $state = $decodedScenario;
            }
        }
    }

    $requestKey = $method . ' ' . $uri;
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $fallbackKey = $method . ' ' . $path;

    $response = null;
    foreach ([$requestKey, $fallbackKey, '*'] as $key) {
        if (!isset($state[$key]) || !is_array($state[$key]) || $state[$key] === []) {
            continue;
        }

        $response = array_shift($state[$key]);
        $state[$key] = array_values($state[$key]);
        break;
    }

    ftruncate($stateHandle, 0);
    rewind($stateHandle);
    fwrite($stateHandle, json_encode($state, JSON_THROW_ON_ERROR));
    fflush($stateHandle);
    flock($stateHandle, LOCK_UN);
    fclose($stateHandle);

    if (!is_array($response)) {
        return [
            'status' => 404,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['error' => 'No mocked response for request'], JSON_THROW_ON_ERROR),
        ];
    }

    return [
        'status' => (int) ($response['status'] ?? 200),
        'headers' => is_array($response['headers'] ?? null) ? $response['headers'] : [],
        'body' => (string) ($response['body'] ?? ''),
    ];
}
