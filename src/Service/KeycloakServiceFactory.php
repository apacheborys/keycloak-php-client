<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service;

use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use Apacheborys\KeycloakPhpClient\Service\Internal\LocalUserMapperResolver;
use Psr\Log\LoggerInterface;

final readonly class KeycloakServiceFactory
{
    /**
     * @param iterable<int, LocalKeycloakUserBridgeMapperInterface> $mappers
     */
    public function create(
        KeycloakHttpClientInterface $httpClient,
        iterable $mappers,
        ?LoggerInterface $logger = null,
    ): KeycloakServiceInterface {
        $mapperResolver = new LocalUserMapperResolver(
            mappers: $mappers,
            logger: $logger,
        );

        $userManagementService = new KeycloakUserManagementService(
            httpClient: $httpClient,
            mapperResolver: $mapperResolver,
            logger: $logger,
        );

        $roleManagementService = new KeycloakRoleManagementService(
            httpClient: $httpClient,
            mapperResolver: $mapperResolver,
            logger: $logger,
        );

        $userIdentifierAttributeService = new KeycloakUserIdentifierAttributeService(
            httpClient: $httpClient,
            logger: $logger,
        );

        $oidcAuthenticationService = new KeycloakOidcAuthenticationService(
            httpClient: $httpClient,
            mapperResolver: $mapperResolver,
        );

        $jwtVerificationService = new KeycloakJwtVerificationService(
            httpClient: $httpClient,
            logger: $logger,
        );

        $realmService = new KeycloakRealmService(httpClient: $httpClient);

        return new KeycloakService(
            userManagementService: $userManagementService,
            roleManagementService: $roleManagementService,
            userIdentifierAttributeService: $userIdentifierAttributeService,
            oidcAuthenticationService: $oidcAuthenticationService,
            jwtVerificationService: $jwtVerificationService,
            realmService: $realmService,
            mapperResolver: $mapperResolver,
        );
    }
}
