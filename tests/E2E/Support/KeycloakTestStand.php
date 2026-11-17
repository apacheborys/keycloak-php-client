<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\Tests\Support\Http\NativePsr18Client;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleRequestFactory;
use Apacheborys\KeycloakPhpClient\Tests\Support\Http\SimpleStreamFactory;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Creates and removes the disposable Keycloak stand for one E2E run, using administrator credentials.
 *
 * It deliberately talks to the Admin REST API directly instead of using the library under test,
 * and it performs exactly the steps of docs/keycloak-api-client-setup.md, so a green E2E run also
 * proves that the documented least-privilege role list is sufficient.
 */
final class KeycloakTestStand
{
    public const string CLIENT_ID = 'keycloak-php-client-e2e';
    public const string REALM_PREFIX = 'keycloak-php-client-e2e-';

    /**
     * Must stay in sync with the permissions table in docs/keycloak-api-client-setup.md.
     */
    public const array SERVICE_ACCOUNT_ROLES = [
        'view-users',
        'manage-users',
        'view-realm',
        'manage-realm',
        'view-clients',
        'manage-clients',
    ];

    private NativePsr18Client $http;
    private SimpleRequestFactory $requests;
    private SimpleStreamFactory $streams;

    private ?string $realm = null;
    private ?string $clientUuid = null;

    public function __construct(private readonly E2EAdminSettings $admin)
    {
        $this->http = new NativePsr18Client();
        $this->requests = new SimpleRequestFactory();
        $this->streams = new SimpleStreamFactory();
    }

    public function provision(): E2EConfig
    {
        $realm = self::REALM_PREFIX . date('Ymd-His') . '-' . bin2hex(random_bytes(3));

        $this->request('POST', '/admin/realms', ['realm' => $realm, 'enabled' => true], expect: 201);
        $this->realm = $realm;

        try {
            $created = $this->request('POST', "/admin/realms/{$realm}/clients", [
                'clientId' => self::CLIENT_ID,
                'name' => 'keycloak-php-client E2E API client',
                'protocol' => 'openid-connect',
                'publicClient' => false,
                'serviceAccountsEnabled' => true,
                'standardFlowEnabled' => false,
                // The suite covers loginUser()/refreshToken(), which use the Password grant.
                'directAccessGrantsEnabled' => true,
            ], expect: 201);
            $this->clientUuid = self::idFromLocation($created);

            $clientPath = "/admin/realms/{$realm}/clients/{$this->clientUuid}";
            $secret = self::path($this->json('GET', "{$clientPath}/client-secret"), 'value');
            $serviceAccountId = self::path($this->json('GET', "{$clientPath}/service-account-user"), 'id');
            $realmManagementId = self::path(
                $this->json('GET', "/admin/realms/{$realm}/clients?clientId=realm-management"),
                0,
                'id',
            );

            $rolesPath = "/admin/realms/{$realm}/clients/{$realmManagementId}/roles";
            // Send only id + name: re-encoding the full representation turns empty JSON objects
            // (e.g. "attributes": {}) into arrays, which Keycloak rejects.
            $roles = array_map(
                function (string $role) use ($rolesPath): array {
                    $representation = $this->json('GET', "{$rolesPath}/{$role}");

                    return ['id' => self::path($representation, 'id'), 'name' => self::path($representation, 'name')];
                },
                self::SERVICE_ACCOUNT_ROLES,
            );
            $this->request(
                'POST',
                "/admin/realms/{$realm}/users/{$serviceAccountId}/role-mappings/clients/{$realmManagementId}",
                $roles,
                expect: 204,
            );

            $serverInfo = $this->json('GET', '/admin/serverinfo');
            $providers = $serverInfo['providers'] ?? null;
            $hashing = is_array($providers) && is_array($providers['password-hashing'] ?? null)
                ? ($providers['password-hashing']['providers'] ?? [])
                : [];
        } catch (\Throwable $exception) {
            $this->destroy();

            throw $exception;
        }

        return new E2EConfig(
            baseUrl: $this->admin->baseUrl,
            realm: $realm,
            clientId: self::CLIENT_ID,
            clientSecret: $secret,
            passwordHashingProviders: is_array($hashing) ? array_map('strval', array_keys($hashing)) : [],
        );
    }

    /**
     * Removes the API client first, then the realm. Safe to call more than once.
     */
    public function destroy(): void
    {
        if ($this->realm === null) {
            return;
        }

        if ($this->clientUuid !== null) {
            $this->request(
                'DELETE',
                "/admin/realms/{$this->realm}/clients/{$this->clientUuid}",
                expect: [204, 404],
            );
            $this->clientUuid = null;
        }

        $this->request('DELETE', "/admin/realms/{$this->realm}", expect: [204, 404]);
        $this->realm = null;
    }

    public function realm(): ?string
    {
        return $this->realm;
    }

    /**
     * @return array<mixed>
     */
    private function json(string $method, string $path): array
    {
        $decoded = json_decode((string) $this->request($method, $path, expect: 200)->getBody(), true);

        return is_array($decoded) ? $decoded : throw new RuntimeException("Non-JSON response from {$path}.");
    }

    /**
     * @param int|list<int> $expect
     */
    private function request(
        string $method,
        string $path,
        mixed $body = null,
        int|array $expect = 200,
    ): ResponseInterface {
        $request = $this->requests->createRequest($method, $this->admin->baseUrl . $path)
            ->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streams->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        $response = $this->http->sendRequest($request);
        if (!in_array($response->getStatusCode(), (array) $expect, true)) {
            throw new RuntimeException(sprintf(
                'E2E stand: %s %s returned HTTP %d: %s',
                $method,
                $path,
                $response->getStatusCode(),
                substr((string) $response->getBody(), 0, 500),
            ));
        }

        return $response;
    }

    /**
     * Fetched per request on purpose: master-realm admin tokens live ~60s by default,
     * and teardown happens after the whole suite has run.
     */
    private function adminToken(): string
    {
        $tokenUrl = "{$this->admin->baseUrl}/realms/{$this->admin->realm}/protocol/openid-connect/token";
        $request = $this->requests
            ->createRequest('POST', $tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streams->createStream(http_build_query([
                'grant_type' => 'password',
                'client_id' => $this->admin->clientId,
                'username' => $this->admin->username,
                'password' => $this->admin->password,
            ])));

        $response = $this->http->sendRequest($request);
        $decoded = json_decode((string) $response->getBody(), true);
        $token = is_array($decoded) ? ($decoded['access_token'] ?? null) : null;
        if ($response->getStatusCode() !== 200 || !is_string($token)) {
            throw new RuntimeException(sprintf(
                'E2E stand: administrator login failed (HTTP %d). Check %s/%s.',
                $response->getStatusCode(),
                E2EAdminSettings::ENV_ADMIN_USERNAME,
                E2EAdminSettings::ENV_ADMIN_PASSWORD,
            ));
        }

        return $token;
    }

    private static function idFromLocation(ResponseInterface $response): string
    {
        $location = $response->getHeaderLine('Location');
        $id = substr($location, (int) strrpos($location, '/') + 1);

        return $id !== '' ? $id : throw new RuntimeException('Location header missing on create.');
    }

    /**
     * Reads a nested string from a decoded JSON document, failing loudly on unexpected shapes.
     *
     * @param array<mixed> $data
     */
    private static function path(array $data, int|string ...$keys): string
    {
        $value = $data;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                throw new RuntimeException('E2E stand: unexpected Admin API response, missing ' . implode('.', $keys));
            }
            $value = $value[$key];
        }

        if (!is_string($value)) {
            throw new RuntimeException('E2E stand: expected string at ' . implode('.', $keys));
        }

        return $value;
    }
}
