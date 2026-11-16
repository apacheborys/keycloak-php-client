#!/usr/bin/env php
<?php

/**
 * Provisions a dedicated realm and a confidential API client for the E2E test suite.
 *
 * This script is the executable form of docs/keycloak-api-client-setup.md. It only needs
 * PHP (no Composer autoload, no curl extension) and is idempotent: re-running it against
 * an already provisioned Keycloak instance leaves the realm/client untouched and prints
 * the connection settings again.
 *
 * Inputs (environment variables):
 *
 *   KEYCLOAK_E2E_BASE_URL        Keycloak base URL              (default: http://localhost:8080)
 *   KEYCLOAK_E2E_ADMIN_USERNAME  master realm admin user        (default: admin)
 *   KEYCLOAK_E2E_ADMIN_PASSWORD  master realm admin password    (required)
 *   KEYCLOAK_E2E_REALM           realm to create/use            (default: keycloak-php-client-e2e)
 *   KEYCLOAK_E2E_CLIENT_ID       client to create/use           (default: php-client-e2e)
 *
 * Output: shell `export` lines for the variables consumed by the E2E suite.
 */

declare(strict_types=1);

const REQUIRED_REALM_MANAGEMENT_ROLES = [
    'manage-users',
    'view-users',
    'manage-realm',
    'view-realm',
    'manage-clients',
    'view-clients',
];

$baseUrl = rtrim(env('KEYCLOAK_E2E_BASE_URL', 'http://localhost:8080'), '/');
$adminUsername = env('KEYCLOAK_E2E_ADMIN_USERNAME', 'admin');
$adminPassword = env('KEYCLOAK_E2E_ADMIN_PASSWORD', '');
$realm = env('KEYCLOAK_E2E_REALM', 'keycloak-php-client-e2e');
$clientId = env('KEYCLOAK_E2E_CLIENT_ID', 'php-client-e2e');

if ($adminPassword === '') {
    fwrite(STDERR, "KEYCLOAK_E2E_ADMIN_PASSWORD is required.\n");
    exit(1);
}

$adminToken = obtainAdminToken($baseUrl, $adminUsername, $adminPassword);
$admin = fn (string $method, string $path, mixed $payload = null): array => request(
    $method,
    $baseUrl . '/admin' . $path,
    $payload,
    ['Authorization: Bearer ' . $adminToken, 'Content-Type: application/json'],
);

// 1. Realm
[$status] = $admin('GET', '/realms/' . rawurlencode($realm));
if ($status === 404) {
    info("Creating realm \"$realm\"");
    expectStatus($admin('POST', '/realms', [
        'realm' => $realm,
        'enabled' => true,
        'displayName' => 'Keycloak PHP Client E2E',
    ]), 201, 'create realm');
} else {
    expectStatus([$status, null], 200, 'read realm');
    info("Realm \"$realm\" already exists");
}

// 2. Allow custom user attributes that are not declared in the user profile.
//    Keycloak >= 24 drops undeclared attributes unless the policy is ENABLED.
//    The profile is round-tripped as objects so that empty `{}` members survive re-encoding.
[$status, $profile] = expectStatus(
    request(
        'GET',
        $baseUrl . '/admin/realms/' . rawurlencode($realm) . '/users/profile',
        null,
        ['Authorization: Bearer ' . $adminToken],
        assoc: false,
    ),
    200,
    'read user profile',
);
if (($profile->unmanagedAttributePolicy ?? null) !== 'ENABLED') {
    info('Enabling unmanaged user attributes on the realm user profile');
    $profile->unmanagedAttributePolicy = 'ENABLED';
    expectStatus($admin('PUT', '/realms/' . rawurlencode($realm) . '/users/profile', $profile), 200, 'update user profile');
}

// 3. Confidential client with a service account (client credentials) and direct access grants
//    (password grant, needed by the legacy loginUser() flow covered by the E2E suite).
[$status, $clients] = expectStatus(
    $admin('GET', '/realms/' . rawurlencode($realm) . '/clients?clientId=' . rawurlencode($clientId)),
    200,
    'search client',
);
if ($clients === []) {
    info("Creating client \"$clientId\"");
    expectStatus($admin('POST', '/realms/' . rawurlencode($realm) . '/clients', [
        'clientId' => $clientId,
        'name' => 'Keycloak PHP Client E2E',
        'protocol' => 'openid-connect',
        'publicClient' => false,
        'serviceAccountsEnabled' => true,
        'directAccessGrantsEnabled' => true,
        'standardFlowEnabled' => false,
        'implicitFlowEnabled' => false,
    ]), 201, 'create client');
    [, $clients] = expectStatus(
        $admin('GET', '/realms/' . rawurlencode($realm) . '/clients?clientId=' . rawurlencode($clientId)),
        200,
        'search client',
    );
} else {
    info("Client \"$clientId\" already exists");
}
$clientUuid = (string) $clients[0]['id'];

[, $secret] = expectStatus(
    $admin('GET', '/realms/' . rawurlencode($realm) . '/clients/' . $clientUuid . '/client-secret'),
    200,
    'read client secret',
);
$clientSecret = (string) ($secret['value'] ?? '');
if ($clientSecret === '') {
    fwrite(STDERR, "Client secret is empty; is the client confidential?\n");
    exit(1);
}

// 4. Grant realm-management roles to the service account user.
[, $serviceAccountUser] = expectStatus(
    $admin('GET', '/realms/' . rawurlencode($realm) . '/clients/' . $clientUuid . '/service-account-user'),
    200,
    'read service account user',
);
$serviceAccountUserId = (string) $serviceAccountUser['id'];

[, $realmManagementClients] = expectStatus(
    $admin('GET', '/realms/' . rawurlencode($realm) . '/clients?clientId=realm-management'),
    200,
    'search realm-management client',
);
$realmManagementUuid = (string) $realmManagementClients[0]['id'];

[, $availableRoles] = expectStatus(
    $admin('GET', '/realms/' . rawurlencode($realm) . '/clients/' . $realmManagementUuid . '/roles'),
    200,
    'read realm-management roles',
);
[, $assignedRoles] = expectStatus(
    $admin(
        'GET',
        '/realms/' . rawurlencode($realm) . '/users/' . $serviceAccountUserId
        . '/role-mappings/clients/' . $realmManagementUuid,
    ),
    200,
    'read assigned service account roles',
);
$assignedRoleNames = array_map(static fn (array $role): string => (string) $role['name'], $assignedRoles);

$rolesToAssign = [];
foreach ($availableRoles as $role) {
    if (in_array($role['name'], REQUIRED_REALM_MANAGEMENT_ROLES, true) && !in_array($role['name'], $assignedRoleNames, true)) {
        $rolesToAssign[] = ['id' => $role['id'], 'name' => $role['name']];
    }
}
if ($rolesToAssign !== []) {
    info('Assigning realm-management roles to the service account: ' . implode(', ', array_column($rolesToAssign, 'name')));
    expectStatus(
        $admin(
            'POST',
            '/realms/' . rawurlencode($realm) . '/users/' . $serviceAccountUserId
            . '/role-mappings/clients/' . $realmManagementUuid,
            $rolesToAssign,
        ),
        204,
        'assign service account roles',
    );
} else {
    info('Service account already has the required realm-management roles');
}

// 5. Smoke-test the client credentials grant with the provisioned client.
[$status] = request(
    'POST',
    $baseUrl . '/realms/' . rawurlencode($realm) . '/protocol/openid-connect/token',
    http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]),
    ['Content-Type: application/x-www-form-urlencoded'],
);
expectStatus([$status, null], 200, 'client credentials smoke test');
info('Client credentials grant works');

echo "\n# Add these to your shell before running: vendor/bin/phpunit --testsuite e2e\n";
echo 'export KEYCLOAK_E2E_BASE_URL=' . escapeshellarg($baseUrl) . "\n";
echo 'export KEYCLOAK_E2E_REALM=' . escapeshellarg($realm) . "\n";
echo 'export KEYCLOAK_E2E_CLIENT_ID=' . escapeshellarg($clientId) . "\n";
echo 'export KEYCLOAK_E2E_CLIENT_SECRET=' . escapeshellarg($clientSecret) . "\n";

function env(string $name, string $default): string
{
    $value = getenv($name);

    return is_string($value) && $value !== '' ? $value : $default;
}

function info(string $message): void
{
    fwrite(STDERR, '[provision] ' . $message . "\n");
}

function obtainAdminToken(string $baseUrl, string $username, string $password): string
{
    [$status, $body] = request(
        'POST',
        $baseUrl . '/realms/master/protocol/openid-connect/token',
        http_build_query([
            'grant_type' => 'password',
            'client_id' => 'admin-cli',
            'username' => $username,
            'password' => $password,
        ]),
        ['Content-Type: application/x-www-form-urlencoded'],
    );
    if ($status !== 200 || !is_string($body['access_token'] ?? null)) {
        fwrite(STDERR, "Admin login failed (HTTP $status). Check KEYCLOAK_E2E_ADMIN_USERNAME / KEYCLOAK_E2E_ADMIN_PASSWORD.\n");
        exit(1);
    }

    return $body['access_token'];
}

/**
 * @param list<string> $headers
 * @return array{0: int, 1: mixed}
 */
function request(string $method, string $url, mixed $payload, array $headers, bool $assoc = true): array
{
    $content = match (true) {
        $payload === null => '',
        is_string($payload) => $payload,
        default => json_encode($payload, JSON_THROW_ON_ERROR),
    };
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        fwrite(STDERR, "Request failed: $method $url (is Keycloak reachable?)\n");
        exit(1);
    }
    /** @var list<string> $http_response_header */
    preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/', $http_response_header[0] ?? '', $matches);
    $status = (int) ($matches[1] ?? 0);
    $decoded = $body === '' ? null : json_decode($body, $assoc);

    return [$status, $decoded ?? $body];
}

/**
 * @param array{0: int, 1: mixed} $response
 * @return array{0: int, 1: mixed}
 */
function expectStatus(array $response, int $expected, string $operation): array
{
    if ($response[0] !== $expected) {
        fwrite(STDERR, sprintf(
            "Failed to %s: expected HTTP %d, got %d. Response: %s\n",
            $operation,
            $expected,
            $response[0],
            is_string($response[1]) ? $response[1] : json_encode($response[1]),
        ));
        exit(1);
    }

    return $response;
}
