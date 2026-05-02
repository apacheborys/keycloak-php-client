<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Service\Fixtures;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;

final class ServiceTestUser implements KeycloakUserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private ?string $keycloakId,
        private string $username = 'user@example.com',
        private string $email = 'user@example.com',
        private bool $emailVerified = true,
        private string $firstName = 'User',
        private string $lastName = 'Example',
        private bool $enabled = true,
        private array $roles = [],
        private int|string|UuidInterface $id = 1,
    ) {
    }

    public function getId(): int|string|UuidInterface
    {
        return $this->id;
    }

    public function getKeycloakId(): ?string
    {
        return $this->keycloakId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return new DateTimeImmutable();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
