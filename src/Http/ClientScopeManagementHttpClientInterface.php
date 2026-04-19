<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopeByIdDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\GetClientScopesDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\UpdateClientScopeProtocolMapperDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\Realm\ClientScopeDto;

interface ClientScopeManagementHttpClientInterface
{
    /**
     * @return list<ClientScopeDto>
     */
    public function getClientScopes(GetClientScopesDto $dto): array;

    public function getClientScopeById(GetClientScopeByIdDto $dto): ClientScopeDto;

    public function createClientScope(CreateClientScopeDto $dto): void;

    public function updateClientScope(UpdateClientScopeDto $dto): void;

    public function deleteClientScope(DeleteClientScopeDto $dto): void;

    public function createClientScopeProtocolMapper(CreateClientScopeProtocolMapperDto $dto): void;

    public function updateClientScopeProtocolMapper(UpdateClientScopeProtocolMapperDto $dto): void;

    public function deleteClientScopeProtocolMapper(DeleteClientScopeProtocolMapperDto $dto): void;
}
