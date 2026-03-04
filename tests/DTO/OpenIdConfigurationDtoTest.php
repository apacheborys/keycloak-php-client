<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Response\OpenIdConfigurationDto;
use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OpenIdConfigurationDtoTest extends TestCase
{
    public function testFromArray(): void
    {
        $dto = OpenIdConfigurationDto::fromArray(
            [
                'issuer' => 'http://localhost:8080/realms/master',
                'authorization_endpoint' => 'http://localhost:8080/realms/master/protocol/openid-connect/auth',
                'token_endpoint' => 'http://localhost:8080/realms/master/protocol/openid-connect/token',
                'jwks_uri' => 'http://localhost:8080/realms/master/protocol/openid-connect/certs',
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
                'claims_parameter_supported' => true,
                'mtls_endpoint_aliases' => [
                    'token_endpoint' => 'http://localhost:8080/realms/master/protocol/openid-connect/token',
                ],
            ]
        );

        self::assertSame('http://localhost:8080/realms/master', $dto->getIssuer());
        self::assertSame(
            'http://localhost:8080/realms/master/protocol/openid-connect/certs',
            $dto->getJwksUri()
        );

        $data = $dto->toArray();
        self::assertSame('http://localhost:8080/realms/master', $data['issuer']);
        self::assertSame(
            'http://localhost:8080/realms/master/protocol/openid-connect/auth',
            $data['authorization_endpoint']
        );
        self::assertSame(
            'http://localhost:8080/realms/master/protocol/openid-connect/token',
            $data['token_endpoint']
        );
        self::assertSame(
            'http://localhost:8080/realms/master/protocol/openid-connect/certs',
            $data['jwks_uri']
        );
        self::assertSame(['authorization_code', 'refresh_token'], $data['grant_types_supported']);
        self::assertTrue($data['claims_parameter_supported']);
        self::assertSame(
            [
                'token_endpoint' => 'http://localhost:8080/realms/master/protocol/openid-connect/token',
            ],
            $data['mtls_endpoint_aliases']
        );
    }

    public function testInvalidJwksUriThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OpenIdConfigurationDto::fromArray(
            [
                'issuer' => 'http://localhost:8080/realms/master',
                'jwks_uri' => 'not-a-url',
            ]
        );
    }
}
