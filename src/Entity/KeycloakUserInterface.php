<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use DateTimeInterface;

interface KeycloakUserInterface
{
    public function getId(): string;

    /**
     * @return string[]
     */
    public function getRealms(): array;

    public function getCreatedAt(): DateTimeInterface;

    public function isDeleted(): bool;
}
