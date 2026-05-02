<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Service\Internal;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use Apacheborys\KeycloakPhpClient\Mapper\LocalKeycloakUserBridgeMapperInterface;
use LogicException;
use Psr\Log\LoggerInterface;

final readonly class LocalUserMapperResolver
{
    public function __construct(
        /**
         * @var LocalKeycloakUserBridgeMapperInterface[]
         */
        private iterable $mappers,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function resolveForUser(KeycloakUserInterface $localUser): LocalKeycloakUserBridgeMapperInterface
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->support(localUser: $localUser)) {
                return $mapper;
            }
        }

        $this->debug(
            message: 'Mapper resolution failed: mapper for local user was not found.',
            context: [
                'user_class' => $localUser::class,
                'local_user_id' => LocalUserIdentifier::logValue($localUser->getId()),
                'keycloak_user_id' => $localUser->getKeycloakId(),
            ],
        );

        throw new LogicException(message: "Can't find proper mapper for " . $localUser::class);
    }

    public function resolveForUserPair(
        KeycloakUserInterface $oldUserVersion,
        KeycloakUserInterface $newUserVersion
    ): LocalKeycloakUserBridgeMapperInterface {
        foreach ($this->mappers as $mapper) {
            if (
                $mapper->support(localUser: $oldUserVersion)
                && $mapper->support(localUser: $newUserVersion)
            ) {
                return $mapper;
            }
        }

        $this->debug(
            message: 'Mapper resolution failed: mapper for user update pair was not found.',
            context: [
                'old_user_class' => $oldUserVersion::class,
                'old_local_user_id' => LocalUserIdentifier::logValue($oldUserVersion->getId()),
                'old_keycloak_user_id' => $oldUserVersion->getKeycloakId(),
                'new_user_class' => $newUserVersion::class,
                'new_local_user_id' => LocalUserIdentifier::logValue($newUserVersion->getId()),
                'new_keycloak_user_id' => $newUserVersion->getKeycloakId(),
            ],
        );

        throw new LogicException(
            message: "Can't find proper mapper for update pair: "
                . $oldUserVersion::class
                . ' and '
                . $newUserVersion::class
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
