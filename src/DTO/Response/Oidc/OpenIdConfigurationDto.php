<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Oidc;

use Assert\Assert;

final readonly class OpenIdConfigurationDto
{
    /**
     * @param list<string> $grantTypesSupported
     * @param list<string> $acrValuesSupported
     * @param list<string> $responseTypesSupported
     * @param list<string> $subjectTypesSupported
     * @param list<string> $idTokenSigningAlgValuesSupported
     * @param list<string> $idTokenEncryptionAlgValuesSupported
     * @param list<string> $idTokenEncryptionEncValuesSupported
     * @param list<string> $userinfoSigningAlgValuesSupported
     * @param list<string> $userinfoEncryptionAlgValuesSupported
     * @param list<string> $userinfoEncryptionEncValuesSupported
     * @param list<string> $requestObjectSigningAlgValuesSupported
     * @param list<string> $requestObjectEncryptionAlgValuesSupported
     * @param list<string> $requestObjectEncryptionEncValuesSupported
     * @param list<string> $responseModesSupported
     * @param list<string> $tokenEndpointAuthMethodsSupported
     * @param list<string> $tokenEndpointAuthSigningAlgValuesSupported
     * @param list<string> $introspectionEndpointAuthMethodsSupported
     * @param list<string> $introspectionEndpointAuthSigningAlgValuesSupported
     * @param list<string> $authorizationSigningAlgValuesSupported
     * @param list<string> $authorizationEncryptionAlgValuesSupported
     * @param list<string> $authorizationEncryptionEncValuesSupported
     * @param list<string> $claimsSupported
     * @param list<string> $claimTypesSupported
     * @param list<string> $scopesSupported
     * @param list<string> $codeChallengeMethodsSupported
     * @param list<string> $revocationEndpointAuthMethodsSupported
     * @param list<string> $revocationEndpointAuthSigningAlgValuesSupported
     * @param list<string> $backchannelTokenDeliveryModesSupported
     * @param list<string> $backchannelAuthenticationRequestSigningAlgValuesSupported
     * @param array<string, string> $mtlsEndpointAliases
     */
    public function __construct(
        private string $issuer,
        private string $jwksUri,
        private ?string $authorizationEndpoint = null,
        private ?string $tokenEndpoint = null,
        private ?string $introspectionEndpoint = null,
        private ?string $userinfoEndpoint = null,
        private ?string $endSessionEndpoint = null,
        private ?bool $frontchannelLogoutSessionSupported = null,
        private ?bool $frontchannelLogoutSupported = null,
        private ?string $checkSessionIframe = null,
        private array $grantTypesSupported = [],
        private array $acrValuesSupported = [],
        private array $responseTypesSupported = [],
        private array $subjectTypesSupported = [],
        private array $idTokenSigningAlgValuesSupported = [],
        private array $idTokenEncryptionAlgValuesSupported = [],
        private array $idTokenEncryptionEncValuesSupported = [],
        private array $userinfoSigningAlgValuesSupported = [],
        private array $userinfoEncryptionAlgValuesSupported = [],
        private array $userinfoEncryptionEncValuesSupported = [],
        private array $requestObjectSigningAlgValuesSupported = [],
        private array $requestObjectEncryptionAlgValuesSupported = [],
        private array $requestObjectEncryptionEncValuesSupported = [],
        private array $responseModesSupported = [],
        private ?string $registrationEndpoint = null,
        private array $tokenEndpointAuthMethodsSupported = [],
        private array $tokenEndpointAuthSigningAlgValuesSupported = [],
        private array $introspectionEndpointAuthMethodsSupported = [],
        private array $introspectionEndpointAuthSigningAlgValuesSupported = [],
        private array $authorizationSigningAlgValuesSupported = [],
        private array $authorizationEncryptionAlgValuesSupported = [],
        private array $authorizationEncryptionEncValuesSupported = [],
        private array $claimsSupported = [],
        private array $claimTypesSupported = [],
        private ?bool $claimsParameterSupported = null,
        private array $scopesSupported = [],
        private ?bool $requestParameterSupported = null,
        private ?bool $requestUriParameterSupported = null,
        private ?bool $requireRequestUriRegistration = null,
        private array $codeChallengeMethodsSupported = [],
        private ?bool $tlsClientCertificateBoundAccessTokens = null,
        private ?string $revocationEndpoint = null,
        private array $revocationEndpointAuthMethodsSupported = [],
        private array $revocationEndpointAuthSigningAlgValuesSupported = [],
        private ?bool $backchannelLogoutSupported = null,
        private ?bool $backchannelLogoutSessionSupported = null,
        private ?string $deviceAuthorizationEndpoint = null,
        private array $backchannelTokenDeliveryModesSupported = [],
        private ?string $backchannelAuthenticationEndpoint = null,
        private array $backchannelAuthenticationRequestSigningAlgValuesSupported = [],
        private ?bool $requirePushedAuthorizationRequests = null,
        private ?string $pushedAuthorizationRequestEndpoint = null,
        private array $mtlsEndpointAliases = [],
        private ?bool $authorizationResponseIssParameterSupported = null,
    ) {
        Assert::that($this->issuer)->notBlank()->url();
        Assert::that($this->jwksUri)->notBlank()->url();
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getJwksUri(): string
    {
        return $this->jwksUri;
    }

    public function getAuthorizationEndpoint(): ?string
    {
        return $this->authorizationEndpoint;
    }

    public function getTokenEndpoint(): ?string
    {
        return $this->tokenEndpoint;
    }

    public function getIntrospectionEndpoint(): ?string
    {
        return $this->introspectionEndpoint;
    }

    public function getUserinfoEndpoint(): ?string
    {
        return $this->userinfoEndpoint;
    }

    public function getEndSessionEndpoint(): ?string
    {
        return $this->endSessionEndpoint;
    }

    public function isFrontchannelLogoutSessionSupported(): ?bool
    {
        return $this->frontchannelLogoutSessionSupported;
    }

    public function isFrontchannelLogoutSupported(): ?bool
    {
        return $this->frontchannelLogoutSupported;
    }

    public function getCheckSessionIframe(): ?string
    {
        return $this->checkSessionIframe;
    }

    /**
     * @return list<string>
     */
    public function getGrantTypesSupported(): array
    {
        return $this->grantTypesSupported;
    }

    /**
     * @return list<string>
     */
    public function getAcrValuesSupported(): array
    {
        return $this->acrValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getResponseTypesSupported(): array
    {
        return $this->responseTypesSupported;
    }

    /**
     * @return list<string>
     */
    public function getSubjectTypesSupported(): array
    {
        return $this->subjectTypesSupported;
    }

    /**
     * @return list<string>
     */
    public function getIdTokenSigningAlgValuesSupported(): array
    {
        return $this->idTokenSigningAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getIdTokenEncryptionAlgValuesSupported(): array
    {
        return $this->idTokenEncryptionAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getIdTokenEncryptionEncValuesSupported(): array
    {
        return $this->idTokenEncryptionEncValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getUserinfoSigningAlgValuesSupported(): array
    {
        return $this->userinfoSigningAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getUserinfoEncryptionAlgValuesSupported(): array
    {
        return $this->userinfoEncryptionAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getUserinfoEncryptionEncValuesSupported(): array
    {
        return $this->userinfoEncryptionEncValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getRequestObjectSigningAlgValuesSupported(): array
    {
        return $this->requestObjectSigningAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getRequestObjectEncryptionAlgValuesSupported(): array
    {
        return $this->requestObjectEncryptionAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getRequestObjectEncryptionEncValuesSupported(): array
    {
        return $this->requestObjectEncryptionEncValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getResponseModesSupported(): array
    {
        return $this->responseModesSupported;
    }

    public function getRegistrationEndpoint(): ?string
    {
        return $this->registrationEndpoint;
    }

    /**
     * @return list<string>
     */
    public function getTokenEndpointAuthMethodsSupported(): array
    {
        return $this->tokenEndpointAuthMethodsSupported;
    }

    /**
     * @return list<string>
     */
    public function getTokenEndpointAuthSigningAlgValuesSupported(): array
    {
        return $this->tokenEndpointAuthSigningAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getIntrospectionEndpointAuthMethodsSupported(): array
    {
        return $this->introspectionEndpointAuthMethodsSupported;
    }

    /**
     * @return list<string>
     */
    public function getIntrospectionEndpointAuthSigningAlgValuesSupported(): array
    {
        return $this->introspectionEndpointAuthSigningAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getAuthorizationSigningAlgValuesSupported(): array
    {
        return $this->authorizationSigningAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getAuthorizationEncryptionAlgValuesSupported(): array
    {
        return $this->authorizationEncryptionAlgValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getAuthorizationEncryptionEncValuesSupported(): array
    {
        return $this->authorizationEncryptionEncValuesSupported;
    }

    /**
     * @return list<string>
     */
    public function getClaimsSupported(): array
    {
        return $this->claimsSupported;
    }

    /**
     * @return list<string>
     */
    public function getClaimTypesSupported(): array
    {
        return $this->claimTypesSupported;
    }

    public function isClaimsParameterSupported(): ?bool
    {
        return $this->claimsParameterSupported;
    }

    /**
     * @return list<string>
     */
    public function getScopesSupported(): array
    {
        return $this->scopesSupported;
    }

    public function isRequestParameterSupported(): ?bool
    {
        return $this->requestParameterSupported;
    }

    public function isRequestUriParameterSupported(): ?bool
    {
        return $this->requestUriParameterSupported;
    }

    public function isRequireRequestUriRegistration(): ?bool
    {
        return $this->requireRequestUriRegistration;
    }

    /**
     * @return list<string>
     */
    public function getCodeChallengeMethodsSupported(): array
    {
        return $this->codeChallengeMethodsSupported;
    }

    public function isTlsClientCertificateBoundAccessTokens(): ?bool
    {
        return $this->tlsClientCertificateBoundAccessTokens;
    }

    public function getRevocationEndpoint(): ?string
    {
        return $this->revocationEndpoint;
    }

    /**
     * @return list<string>
     */
    public function getRevocationEndpointAuthMethodsSupported(): array
    {
        return $this->revocationEndpointAuthMethodsSupported;
    }

    /**
     * @return list<string>
     */
    public function getRevocationEndpointAuthSigningAlgValuesSupported(): array
    {
        return $this->revocationEndpointAuthSigningAlgValuesSupported;
    }

    public function isBackchannelLogoutSupported(): ?bool
    {
        return $this->backchannelLogoutSupported;
    }

    public function isBackchannelLogoutSessionSupported(): ?bool
    {
        return $this->backchannelLogoutSessionSupported;
    }

    public function getDeviceAuthorizationEndpoint(): ?string
    {
        return $this->deviceAuthorizationEndpoint;
    }

    /**
     * @return list<string>
     */
    public function getBackchannelTokenDeliveryModesSupported(): array
    {
        return $this->backchannelTokenDeliveryModesSupported;
    }

    public function getBackchannelAuthenticationEndpoint(): ?string
    {
        return $this->backchannelAuthenticationEndpoint;
    }

    /**
     * @return list<string>
     */
    public function getBackchannelAuthenticationRequestSigningAlgValuesSupported(): array
    {
        return $this->backchannelAuthenticationRequestSigningAlgValuesSupported;
    }

    public function isRequirePushedAuthorizationRequests(): ?bool
    {
        return $this->requirePushedAuthorizationRequests;
    }

    public function getPushedAuthorizationRequestEndpoint(): ?string
    {
        return $this->pushedAuthorizationRequestEndpoint;
    }

    /**
     * @return array<string, string>
     */
    public function getMtlsEndpointAliases(): array
    {
        return $this->mtlsEndpointAliases;
    }

    public function isAuthorizationResponseIssParameterSupported(): ?bool
    {
        return $this->authorizationResponseIssParameterSupported;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'introspection_endpoint' => $this->introspectionEndpoint,
            'userinfo_endpoint' => $this->userinfoEndpoint,
            'end_session_endpoint' => $this->endSessionEndpoint,
            'frontchannel_logout_session_supported' => $this->frontchannelLogoutSessionSupported,
            'frontchannel_logout_supported' => $this->frontchannelLogoutSupported,
            'jwks_uri' => $this->jwksUri,
            'check_session_iframe' => $this->checkSessionIframe,
            'grant_types_supported' => $this->grantTypesSupported,
            'acr_values_supported' => $this->acrValuesSupported,
            'response_types_supported' => $this->responseTypesSupported,
            'subject_types_supported' => $this->subjectTypesSupported,
            'id_token_signing_alg_values_supported' => $this->idTokenSigningAlgValuesSupported,
            'id_token_encryption_alg_values_supported' => $this->idTokenEncryptionAlgValuesSupported,
            'id_token_encryption_enc_values_supported' => $this->idTokenEncryptionEncValuesSupported,
            'userinfo_signing_alg_values_supported' => $this->userinfoSigningAlgValuesSupported,
            'userinfo_encryption_alg_values_supported' => $this->userinfoEncryptionAlgValuesSupported,
            'userinfo_encryption_enc_values_supported' => $this->userinfoEncryptionEncValuesSupported,
            'request_object_signing_alg_values_supported' => $this->requestObjectSigningAlgValuesSupported,
            'request_object_encryption_alg_values_supported' => $this->requestObjectEncryptionAlgValuesSupported,
            'request_object_encryption_enc_values_supported' => $this->requestObjectEncryptionEncValuesSupported,
            'response_modes_supported' => $this->responseModesSupported,
            'registration_endpoint' => $this->registrationEndpoint,
            'token_endpoint_auth_methods_supported' => $this->tokenEndpointAuthMethodsSupported,
            'token_endpoint_auth_signing_alg_values_supported' => $this->tokenEndpointAuthSigningAlgValuesSupported,
            'introspection_endpoint_auth_methods_supported' => $this->introspectionEndpointAuthMethodsSupported,
            'introspection_endpoint_auth_signing_alg_values_supported'
                => $this->introspectionEndpointAuthSigningAlgValuesSupported,
            'authorization_signing_alg_values_supported' => $this->authorizationSigningAlgValuesSupported,
            'authorization_encryption_alg_values_supported' => $this->authorizationEncryptionAlgValuesSupported,
            'authorization_encryption_enc_values_supported' => $this->authorizationEncryptionEncValuesSupported,
            'claims_supported' => $this->claimsSupported,
            'claim_types_supported' => $this->claimTypesSupported,
            'claims_parameter_supported' => $this->claimsParameterSupported,
            'scopes_supported' => $this->scopesSupported,
            'request_parameter_supported' => $this->requestParameterSupported,
            'request_uri_parameter_supported' => $this->requestUriParameterSupported,
            'require_request_uri_registration' => $this->requireRequestUriRegistration,
            'code_challenge_methods_supported' => $this->codeChallengeMethodsSupported,
            'tls_client_certificate_bound_access_tokens' => $this->tlsClientCertificateBoundAccessTokens,
            'revocation_endpoint' => $this->revocationEndpoint,
            'revocation_endpoint_auth_methods_supported' => $this->revocationEndpointAuthMethodsSupported,
            'revocation_endpoint_auth_signing_alg_values_supported'
                => $this->revocationEndpointAuthSigningAlgValuesSupported,
            'backchannel_logout_supported' => $this->backchannelLogoutSupported,
            'backchannel_logout_session_supported' => $this->backchannelLogoutSessionSupported,
            'device_authorization_endpoint' => $this->deviceAuthorizationEndpoint,
            'backchannel_token_delivery_modes_supported' => $this->backchannelTokenDeliveryModesSupported,
            'backchannel_authentication_endpoint' => $this->backchannelAuthenticationEndpoint,
            'backchannel_authentication_request_signing_alg_values_supported'
                => $this->backchannelAuthenticationRequestSigningAlgValuesSupported,
            'require_pushed_authorization_requests' => $this->requirePushedAuthorizationRequests,
            'pushed_authorization_request_endpoint' => $this->pushedAuthorizationRequestEndpoint,
            'mtls_endpoint_aliases' => $this->mtlsEndpointAliases,
            'authorization_response_iss_parameter_supported' => $this->authorizationResponseIssParameterSupported,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            issuer: self::requiredUrl(data: $data, key: 'issuer'),
            jwksUri: self::requiredUrl(data: $data, key: 'jwks_uri'),
            authorizationEndpoint: self::optionalUrl(data: $data, key: 'authorization_endpoint'),
            tokenEndpoint: self::optionalUrl(data: $data, key: 'token_endpoint'),
            introspectionEndpoint: self::optionalUrl(data: $data, key: 'introspection_endpoint'),
            userinfoEndpoint: self::optionalUrl(data: $data, key: 'userinfo_endpoint'),
            endSessionEndpoint: self::optionalUrl(data: $data, key: 'end_session_endpoint'),
            frontchannelLogoutSessionSupported: self::optionalBool(
                data: $data,
                key: 'frontchannel_logout_session_supported'
            ),
            frontchannelLogoutSupported: self::optionalBool(data: $data, key: 'frontchannel_logout_supported'),
            checkSessionIframe: self::optionalUrl(data: $data, key: 'check_session_iframe'),
            grantTypesSupported: self::optionalStringList(data: $data, key: 'grant_types_supported'),
            acrValuesSupported: self::optionalStringList(data: $data, key: 'acr_values_supported'),
            responseTypesSupported: self::optionalStringList(data: $data, key: 'response_types_supported'),
            subjectTypesSupported: self::optionalStringList(data: $data, key: 'subject_types_supported'),
            idTokenSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'id_token_signing_alg_values_supported'
            ),
            idTokenEncryptionAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'id_token_encryption_alg_values_supported'
            ),
            idTokenEncryptionEncValuesSupported: self::optionalStringList(
                data: $data,
                key: 'id_token_encryption_enc_values_supported'
            ),
            userinfoSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'userinfo_signing_alg_values_supported'
            ),
            userinfoEncryptionAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'userinfo_encryption_alg_values_supported'
            ),
            userinfoEncryptionEncValuesSupported: self::optionalStringList(
                data: $data,
                key: 'userinfo_encryption_enc_values_supported'
            ),
            requestObjectSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'request_object_signing_alg_values_supported'
            ),
            requestObjectEncryptionAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'request_object_encryption_alg_values_supported'
            ),
            requestObjectEncryptionEncValuesSupported: self::optionalStringList(
                data: $data,
                key: 'request_object_encryption_enc_values_supported'
            ),
            responseModesSupported: self::optionalStringList(data: $data, key: 'response_modes_supported'),
            registrationEndpoint: self::optionalUrl(data: $data, key: 'registration_endpoint'),
            tokenEndpointAuthMethodsSupported: self::optionalStringList(
                data: $data,
                key: 'token_endpoint_auth_methods_supported'
            ),
            tokenEndpointAuthSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'token_endpoint_auth_signing_alg_values_supported'
            ),
            introspectionEndpointAuthMethodsSupported: self::optionalStringList(
                data: $data,
                key: 'introspection_endpoint_auth_methods_supported'
            ),
            introspectionEndpointAuthSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'introspection_endpoint_auth_signing_alg_values_supported'
            ),
            authorizationSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'authorization_signing_alg_values_supported'
            ),
            authorizationEncryptionAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'authorization_encryption_alg_values_supported'
            ),
            authorizationEncryptionEncValuesSupported: self::optionalStringList(
                data: $data,
                key: 'authorization_encryption_enc_values_supported'
            ),
            claimsSupported: self::optionalStringList(data: $data, key: 'claims_supported'),
            claimTypesSupported: self::optionalStringList(data: $data, key: 'claim_types_supported'),
            claimsParameterSupported: self::optionalBool(data: $data, key: 'claims_parameter_supported'),
            scopesSupported: self::optionalStringList(data: $data, key: 'scopes_supported'),
            requestParameterSupported: self::optionalBool(data: $data, key: 'request_parameter_supported'),
            requestUriParameterSupported: self::optionalBool(data: $data, key: 'request_uri_parameter_supported'),
            requireRequestUriRegistration: self::optionalBool(data: $data, key: 'require_request_uri_registration'),
            codeChallengeMethodsSupported: self::optionalStringList(
                data: $data,
                key: 'code_challenge_methods_supported'
            ),
            tlsClientCertificateBoundAccessTokens: self::optionalBool(
                data: $data,
                key: 'tls_client_certificate_bound_access_tokens'
            ),
            revocationEndpoint: self::optionalUrl(data: $data, key: 'revocation_endpoint'),
            revocationEndpointAuthMethodsSupported: self::optionalStringList(
                data: $data,
                key: 'revocation_endpoint_auth_methods_supported'
            ),
            revocationEndpointAuthSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'revocation_endpoint_auth_signing_alg_values_supported'
            ),
            backchannelLogoutSupported: self::optionalBool(data: $data, key: 'backchannel_logout_supported'),
            backchannelLogoutSessionSupported: self::optionalBool(
                data: $data,
                key: 'backchannel_logout_session_supported'
            ),
            deviceAuthorizationEndpoint: self::optionalUrl(data: $data, key: 'device_authorization_endpoint'),
            backchannelTokenDeliveryModesSupported: self::optionalStringList(
                data: $data,
                key: 'backchannel_token_delivery_modes_supported'
            ),
            backchannelAuthenticationEndpoint: self::optionalUrl(
                data: $data,
                key: 'backchannel_authentication_endpoint'
            ),
            backchannelAuthenticationRequestSigningAlgValuesSupported: self::optionalStringList(
                data: $data,
                key: 'backchannel_authentication_request_signing_alg_values_supported'
            ),
            requirePushedAuthorizationRequests: self::optionalBool(
                data: $data,
                key: 'require_pushed_authorization_requests'
            ),
            pushedAuthorizationRequestEndpoint: self::optionalUrl(
                data: $data,
                key: 'pushed_authorization_request_endpoint'
            ),
            mtlsEndpointAliases: self::optionalStringMap(data: $data, key: 'mtls_endpoint_aliases'),
            authorizationResponseIssParameterSupported: self::optionalBool(
                data: $data,
                key: 'authorization_response_iss_parameter_supported'
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requiredUrl(array $data, string $key): string
    {
        Assert::that($data)->keyExists($key);
        Assert::that($data[$key])->string()->notBlank()->url();

        /** @var string $value */
        $value = $data[$key];

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalUrl(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        Assert::that($data[$key])->string()->notBlank()->url();

        /** @var string $value */
        $value = $data[$key];

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalBool(array $data, string $key): ?bool
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        Assert::that($data[$key])->boolean();

        /** @var bool $value */
        $value = $data[$key];

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function optionalStringList(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return [];
        }

        Assert::that($data[$key])->isArray();

        /** @var array<int, mixed> $values */
        $values = $data[$key];

        $result = [];
        foreach ($values as $value) {
            Assert::that($value)->string()->notBlank();
            /** @var string $value */
            $result[] = $value;
        }

        return array_values($result);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function optionalStringMap(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return [];
        }

        Assert::that($data[$key])->isArray();

        /** @var array<mixed> $values */
        $values = $data[$key];

        $result = [];
        foreach ($values as $mapKey => $value) {
            Assert::that($mapKey)->string()->notBlank();
            Assert::that($value)->string()->notBlank()->url();
            /** @var string $mapKey */
            /** @var string $value */
            $result[$mapKey] = $value;
        }

        return $result;
    }
}
