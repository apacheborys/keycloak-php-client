<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;

interface KeycloakUserInterface
{
    /**
     * Returns the stable local application user identifier.
     */
    public function getId(): int|string|UuidInterface;

    /**
     * Returns the Keycloak user identifier when it is persisted locally.
     */
    public function getKeycloakId(): ?string;

    public function getUsername(): string;

    public function getEmail(): string;

    public function isEmailVerified(): bool;

    public function getFirstName(): string;

    public function getLastName(): string;

    /**
     * @return string[]
     */
    public function getRoles(): array;

    public function getCreatedAt(): DateTimeInterface;

    public function isEnabled(): bool;
}
