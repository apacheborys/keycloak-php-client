<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use DateTimeInterface;

interface KeycloakUserInterface
{
    public function getId(): string;

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
