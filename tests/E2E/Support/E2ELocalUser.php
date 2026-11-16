<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use Apacheborys\KeycloakPhpClient\Entity\KeycloakUserInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Ramsey\Uuid\UuidInterface;

/**
 * Minimal stand-in for an application's own user entity.
 *
 * Immutable; the with*() methods produce the "new version" passed to updateUser().
 */
final readonly class E2ELocalUser implements KeycloakUserInterface
{
    /**
     * @param list<string> $roles
     * @param array<string, string> $extraAttributes additional Keycloak user attributes set on creation
     */
    public function __construct(
        private string $id,
        private string $username,
        private string $email,
        private string $firstName,
        private string $lastName,
        private ?string $keycloakId = null,
        private bool $emailVerified = true,
        private bool $enabled = true,
        private array $roles = [],
        private array $extraAttributes = [],
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
    }

    #[Override]
    public function getId(): int|string|UuidInterface
    {
        return $this->id;
    }

    #[Override]
    public function getKeycloakId(): ?string
    {
        return $this->keycloakId;
    }

    #[Override]
    public function getUsername(): string
    {
        return $this->username;
    }

    #[Override]
    public function getEmail(): string
    {
        return $this->email;
    }

    #[Override]
    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    #[Override]
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    #[Override]
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[Override]
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    #[Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, string>
     */
    public function getExtraAttributes(): array
    {
        return $this->extraAttributes;
    }

    public function withKeycloakId(string $keycloakId): self
    {
        return $this->with(keycloakId: $keycloakId);
    }

    public function withName(string $firstName, string $lastName): self
    {
        return $this->with(firstName: $firstName, lastName: $lastName);
    }

    /**
     * @param list<string> $roles
     */
    public function withRoles(array $roles): self
    {
        return $this->with(roles: $roles);
    }

    public function withEnabled(bool $enabled): self
    {
        return $this->with(enabled: $enabled);
    }

    /**
     * @param list<string>|null $roles
     */
    private function with(
        ?string $keycloakId = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?array $roles = null,
        ?bool $enabled = null,
    ): self {
        return new self(
            id: $this->id,
            username: $this->username,
            email: $this->email,
            firstName: $firstName ?? $this->firstName,
            lastName: $lastName ?? $this->lastName,
            keycloakId: $keycloakId ?? $this->keycloakId,
            emailVerified: $this->emailVerified,
            enabled: $enabled ?? $this->enabled,
            roles: $roles ?? $this->roles,
            extraAttributes: $this->extraAttributes,
            createdAt: $this->createdAt,
        );
    }
}
