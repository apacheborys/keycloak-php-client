<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use DateTimeImmutable;
use DateTimeInterface;

final class ServiceTestUser implements KeycloakUserInterface
{
    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return 'user@example.com';
    }

    public function getEmail(): string
    {
        return 'user@example.com';
    }

    public function isEmailVerified(): bool
    {
        return true;
    }

    public function getFirstName(): string
    {
        return 'User';
    }

    public function getLastName(): string
    {
        return 'Example';
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return new DateTimeImmutable();
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
