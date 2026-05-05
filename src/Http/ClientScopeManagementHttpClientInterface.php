<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopeProtocolMappersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ClientScope\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopesProtocolMapperDto;

interface ClientScopeManagementHttpClientInterface
{
    /**
     * @return list<ClientScopeDto>
     */
    public function getClientScopes(GetClientScopesDto $dto): array;

    public function getClientScopeById(GetClientScopeByIdDto $dto): ClientScopeDto;

    /**
     * @return list<ClientScopesProtocolMapperDto>
     */
    public function getClientScopeProtocolMappers(GetClientScopeProtocolMappersDto $dto): array;

    public function createClientScope(CreateClientScopeDto $dto): void;

    public function updateClientScope(UpdateClientScopeDto $dto): void;

    public function deleteClientScope(DeleteClientScopeDto $dto): void;

    public function createClientScopeProtocolMapper(CreateClientScopeProtocolMapperDto $dto): void;

    public function updateClientScopeProtocolMapper(UpdateClientScopeProtocolMapperDto $dto): void;

    public function deleteClientScopeProtocolMapper(DeleteClientScopeProtocolMapperDto $dto): void;
}
