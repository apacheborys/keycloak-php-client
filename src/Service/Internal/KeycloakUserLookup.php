<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service\Internal;

use Apacheborys\KeycloakPhpClient\DTO\Request\User\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\User\AttributeValueDto;
use Apacheborys\KeycloakPhpClient\Entity\KeycloakUser;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakUserLookup
{
    public function __construct(
        private KeycloakHttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function resolveUserId(
        string $realm,
        ?string $keycloakId,
        AttributeValueDto $localUserIdAttribute,
        string $operation,
    ): UuidInterface {
        if ($keycloakId !== null) {
            return $this->keycloakIdFromString(
                keycloakId: $keycloakId,
                localUserIdAttribute: $localUserIdAttribute,
                operation: $operation,
            );
        }

        return Uuid::fromString(
            $this->findSingleUserByLocalId(
                realm: $realm,
                localUserIdAttribute: $localUserIdAttribute,
                operation: $operation,
            )->getKeycloakId()
        );
    }

    private function keycloakIdFromString(
        string $keycloakId,
        AttributeValueDto $localUserIdAttribute,
        string $operation,
    ): UuidInterface {
        if (Uuid::isValid($keycloakId)) {
            return Uuid::fromString($keycloakId);
        }

        $this->debug(
            message: 'User lookup failed: local user exposes an invalid Keycloak user identifier.',
            context: [
                'operation' => $operation,
                'attribute_name' => $localUserIdAttribute->getAttributeName(),
                'local_user_id_values' => $localUserIdAttribute->getNormalizedValues(),
                'keycloak_user_id' => $keycloakId,
            ],
        );

        throw new LogicException('Local user exposes an invalid Keycloak user id.');
    }

    private function findSingleUserByLocalId(
        string $realm,
        AttributeValueDto $localUserIdAttribute,
        string $operation,
    ): KeycloakUser {
        $localUserIdAttributeName = $localUserIdAttribute->getAttributeName();

        if (trim($localUserIdAttributeName) === '') {
            $this->debug(
                message: 'User lookup failed: mapper returned an empty local user id attribute name.',
                context: [
                    'realm' => $realm,
                    'operation' => $operation,
                    'local_user_id_values' => $localUserIdAttribute->getNormalizedValues(),
                ],
            );

            throw new LogicException('Mapper local user id attribute name must not be empty.');
        }

        $localUserIdValues = $localUserIdAttribute->getNormalizedValues();
        if (count($localUserIdValues) !== 1) {
            $this->debug(
                message: 'User lookup failed: local user id attribute must contain exactly one value.',
                context: [
                    'realm' => $realm,
                    'operation' => $operation,
                    'attribute_name' => $localUserIdAttributeName,
                    'local_user_id_values' => $localUserIdValues,
                ],
            );

            throw new LogicException('Local user id attribute must contain exactly one value.');
        }

        $localUserId = $localUserIdValues[0];
        if ($localUserId === '') {
            $this->debug(
                message: 'User lookup failed: local user id is empty.',
                context: [
                    'realm' => $realm,
                    'operation' => $operation,
                    'attribute_name' => $localUserIdAttributeName,
                ],
            );

            throw new LogicException('Local user id must not be empty.');
        }

        $users = $this->httpClient->getUsers(
            dto: new SearchUsersDto(
                realm: $realm,
                customAttributes: [
                    $localUserIdAttributeName => $localUserId,
                ],
                max: 2,
                exact: true,
            ),
        );

        if (count($users) === 1) {
            return $users[0];
        }

        $this->debug(
            message: 'User lookup by local identifier did not return exactly one result.',
            context: [
                'realm' => $realm,
                'operation' => $operation,
                'attribute_name' => $localUserIdAttributeName,
                'local_user_id_values' => $localUserIdValues,
                'found_user_ids' => array_values(array_map(
                    static fn (KeycloakUser $user): string => $user->getKeycloakId(),
                    $users,
                )),
            ],
        );

        throw new LogicException(
            message: sprintf(
                'Expected exactly one Keycloak user with %s "%s" in realm "%s" during %s, got %d.',
                $localUserIdAttributeName,
                $localUserId,
                $realm,
                $operation,
                count($users),
            )
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, array $context = []): void
    {
        $this->logger?->debug(message: $message, context: $context);
    }
}
