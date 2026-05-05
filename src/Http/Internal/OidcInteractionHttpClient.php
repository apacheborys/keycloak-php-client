<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\Oidc\OidcTokenRequestDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwkDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\JwksDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OidcTokenResponseDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Oidc\OpenIdConfigurationDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakRealm;
use Apacheborys\KeycloakPhpClient\Http\OidcInteractionHttpClientInterface;
use Assert\Assert;
use RuntimeException;

final readonly class OidcInteractionHttpClient implements OidcInteractionHttpClientInterface
{
    public function __construct(
        private KeycloakHttpCore $httpCore,
        private AccessTokenProvider $accessTokenProvider,
    ) {
    }

    #[\Override]
    public function getOpenIdConfiguration(string $realm): OpenIdConfigurationDto
    {
        $endpoint = $this->httpCore->buildEndpoint(path: '/realms/' . $realm . '/.well-known/openid-configuration');
        $request = $this->httpCore->createRequest(method: 'GET', endpoint: $endpoint);

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak OpenID configuration request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        return OpenIdConfigurationDto::fromArray(data: $data);
    }

    #[\Override]
    public function getJwk(
        string $kid,
        string $jwksUri,
    ): ?JwkDto {
        $jwks = $this->getJwks(
            jwksUri: $jwksUri,
        );

        return $jwks->findByKid(kid: $kid);
    }

    #[\Override]
    public function getJwks(string $jwksUri): JwksDto
    {
        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $jwksUri,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak JWKS request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);
        return JwksDto::fromArray(data: $data);
    }

    /**
     * @return list<KeycloakRealm>
     */
    #[\Override]
    public function getAvailableRealms(): array
    {
        $token = $this->accessTokenProvider->getAccessToken();
        $parameters = [
            'briefRepresentation' => 'true',
        ];

        $endpoint = $this->httpCore->buildEndpoint(
            path: '/admin/realms',
            query: http_build_query(
                data: $parameters,
                arg_separator: '&',
                encoding_type: PHP_QUERY_RFC3986
            ),
        );

        $request = $this->httpCore->createRequest(
            method: 'GET',
            endpoint: $endpoint,
            headers: ['Authorization' => 'Bearer ' . $token->getRawToken()],
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf('Keycloak available realms request failed with status %d: %s', $statusCode, $body)
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        /** @var array<int, array<string, mixed>> $data */
        $realms = [];
        foreach ($data as $realmData) {
            Assert::that($realmData)->isArray();
            $realms[] = KeycloakRealm::fromArray(data: $realmData);
        }

        return $realms;
    }

    #[\Override]
    public function requestTokenByPassword(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->requestToken(dto: $dto);
    }

    #[\Override]
    public function refreshToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        return $this->requestToken(dto: $dto);
    }

    private function requestToken(OidcTokenRequestDto $dto): OidcTokenResponseDto
    {
        $endpoint = $this->httpCore->buildEndpoint(
            path: '/realms/' . $dto->getRealm() . '/protocol/openid-connect/token'
        );

        $payload = http_build_query(
            data: $dto->toFormParams(),
            numeric_prefix: '',
            arg_separator: '&',
            encoding_type: PHP_QUERY_RFC3986
        );

        $request = $this->httpCore->createRequest(
            method: 'POST',
            endpoint: $endpoint,
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: $payload,
        );

        $response = $this->httpCore->sendRequest(request: $request);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                message: sprintf(
                    'Keycloak %s request failed with status %d: %s',
                    $dto->getGrantType()->value,
                    $statusCode,
                    $body
                )
            );
        }

        $data = $this->httpCore->decodeJson(body: $body);

        return OidcTokenResponseDto::fromArray(data: $data);
    }
}
