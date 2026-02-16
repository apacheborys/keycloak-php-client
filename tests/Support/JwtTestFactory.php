<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support;

final class JwtTestFactory
{
    /**
     * @param array<string, mixed> $payloadOverrides
     * @param array<string, mixed> $headerOverrides
     */
    public static function buildJwtToken(array $payloadOverrides = [], array $headerOverrides = []): string
    {
        $header = array_replace(
            [
                'alg' => 'RS256',
                'typ' => 'JWT',
                'kid' => 'kid',
            ],
            $headerOverrides,
        );

        $payload = array_replace(
            [
                'exp' => time() + 3600,
                'iat' => time(),
                'jti' => 'f9b4b801-bb78-4167-be60-b42d453332e7',
                'iss' => 'http://localhost:8080/realms/master',
                'aud' => ['account'],
                'sub' => '92a372d5-c338-4e77-a1b3-08771241036e',
                'typ' => 'Bearer',
                'azp' => 'backend',
                'acr' => 1,
                'realm_access' => ['roles' => ['role']],
                'resource_access' => [
                    'backend' => ['roles' => ['role']],
                    'account' => ['roles' => ['role']],
                ],
                'scope' => 'email profile',
                'email_verified' => true,
                'clientHost' => '127.0.0.1',
                'preferred_username' => 'user@example.com',
                'clientAddress' => '127.0.0.1',
                'client_id' => 'backend',
            ],
            $payloadOverrides,
        );

        return self::base64UrlEncode($header) . '.' .
            self::base64UrlEncode($payload) . '.' .
            self::base64UrlEncode(['sig' => 'signature']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function base64UrlEncode(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
