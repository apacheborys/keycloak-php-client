<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Apacheborys\KeycloakPhpClient\Model\KeycloakUserAccess;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakRequiredAction;
use Assert\Assert;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakUser implements KeycloakUserInterface, JsonSerializable
{
    /**
     * @param list<KeycloakCredentialType> $disableableCredentialTypes
     * @param list<KeycloakRequiredAction> $requiredActions
     * @param list<string> $roles
     */
    public function __construct(
        private UuidInterface $id,
        private string $username,
        private string $firstName,
        private string $lastName,
        private string $email,
        private bool $emailVerified,
        private DateTimeImmutable $createdTimestamp,
        private bool $enabled,
        private bool $totp,
        private array $disableableCredentialTypes,
        private array $requiredActions,
        private int $notBefore,
        private KeycloakUserAccess $access,
        private array $roles = [],
    ) {
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    #[\Override]
    public function getUsername(): string
    {
        return $this->username;
    }

    #[\Override]
    public function getEmail(): string
    {
        return $this->email;
    }

    #[\Override]
    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    #[\Override]
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    #[\Override]
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdTimestamp;
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isTotp(): bool
    {
        return $this->totp;
    }

    public function getNotBefore(): int
    {
        return $this->notBefore;
    }

    public function getAccess(): KeycloakUserAccess
    {
        return $this->access;
    }

    /**
     * @return list<KeycloakCredentialType>
     */
    public function getDisableableCredentialTypes(): array
    {
        return $this->disableableCredentialTypes;
    }

    /**
     * @return list<KeycloakRequiredAction>
     */
    public function getRequiredActions(): array
    {
        return $this->requiredActions;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        $createdTimestampMs = ((int) $this->createdTimestamp->format('U')) * 1000
            + (int) $this->createdTimestamp->format('v');

        return [
            'id' => $this->id->toString(),
            'username' => $this->username,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'emailVerified' => $this->emailVerified,
            'createdTimestamp' => $createdTimestampMs,
            'enabled' => $this->enabled,
            'totp' => $this->totp,
            'disableableCredentialTypes' => array_map(
                static fn (KeycloakCredentialType $type): string => $type->value(),
                $this->disableableCredentialTypes
            ),
            'requiredActions' => array_map(
                static fn (KeycloakRequiredAction $action): string => $action->value(),
                $this->requiredActions
            ),
            'notBefore' => $this->notBefore,
            'access' => $this->access->jsonSerialize(),
            'realmRoles' => $this->roles,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Assert::that(value: $data)->keyExists(key: 'id');
        Assert::that(value: $data)->keyExists(key: 'username');
        Assert::that(value: $data)->keyExists(key: 'createdTimestamp');

        Assert::that(value: $data['id'])->string()->notBlank();
        Assert::that(value: $data['username'])->string()->notBlank();
        Assert::that(value: $data['createdTimestamp'])->integer()->greaterOrEqualThan(limit: 0);

        /**
         * @var array{
         *  id: non-empty-string,
         *  username: non-empty-string,
         *  createdTimestamp: int
         * } $data
         */

        Assert::that(value: Uuid::isValid(uuid: $data['id']))->true();
        
        $createdAt = self::fromTimestampMs(timestampMs: $data['createdTimestamp']);

        $access = self::buildAccess(data: $data['access'] ?? null);
        $disableableCredentialTypes = self::buildCredentialTypes(data: $data['disableableCredentialTypes'] ?? []);
        $requiredActions = self::buildRequiredActions(data: $data['requiredActions'] ?? []);
        $roles = self::buildRoles(data: $data);

        return new self(
            id: Uuid::fromString(uuid: $data['id']),
            username: $data['username'],
            firstName: self::stringOrDefault(data: $data, key: 'firstName'),
            lastName: self::stringOrDefault(data: $data, key: 'lastName'),
            email: self::stringOrDefault(data: $data, key: 'email'),
            emailVerified: self::boolOrDefault(data: $data, key: 'emailVerified', default: false),
            createdTimestamp: $createdAt,
            enabled: self::boolOrDefault(data: $data, key: 'enabled', default: false),
            totp: self::boolOrDefault(data: $data, key: 'totp', default: false),
            disableableCredentialTypes: $disableableCredentialTypes,
            requiredActions: $requiredActions,
            notBefore: self::intOrDefault(data: $data, key: 'notBefore', default: 0),
            access: $access,
            roles: $roles
        );
    }

    private static function fromTimestampMs(int $timestampMs): DateTimeImmutable
    {
        $createdAt = DateTimeImmutable::createFromFormat(format: 'U.u', datetime: sprintf(format: '%.6f', values: $timestampMs / 1000));
        Assert::that(value: $createdAt)->isInstanceOf(className: DateTimeImmutable::class);

        /** @var DateTimeImmutable $createdAt */

        return $createdAt;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringOrDefault(array $data, string $key, string $default = ''): string
    {
        if (!array_key_exists(key: $key, array: $data) || $data[$key] === null) {
            return $default;
        }

        Assert::that(value: $data[$key])->string();

        /** @phpstan-ignore-next-line */
        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function boolOrDefault(array $data, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        Assert::that(value: $data[$key])->boolean();

        /** @phpstan-ignore-next-line */
        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function intOrDefault(array $data, string $key, int $default): int
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        Assert::that(value: $data[$key])->integer();

        /** @phpstan-ignore-next-line */
        return $data[$key];
    }

    private static function buildAccess(mixed $data): KeycloakUserAccess
    {
        if (is_array($data)) {
            return KeycloakUserAccess::fromArray(data: $data);
        }

        return new KeycloakUserAccess(
            manageGroupMembership: false,
            view: false,
            mapRoles: false,
            impersonate: false,
            manage: false
        );
    }

    /**
     * @return list<KeycloakCredentialType>
     */
    private static function buildCredentialTypes(mixed $data): array
    {
        $values = self::stringList(data: $data);
        $types = [];

        foreach ($values as $value) {
            $types[] = KeycloakCredentialType::fromString(value: $value);
        }

        return $types;
    }

    /**
     * @return list<KeycloakRequiredAction>
     */
    private static function buildRequiredActions(mixed $data): array
    {
        $values = self::stringList(data: $data);
        $actions = [];

        foreach ($values as $value) {
            $actions[] = KeycloakRequiredAction::fromString(value: $value);
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function buildRoles(array $data): array
    {
        $roles = self::stringList(data: $data['realmRoles'] ?? $data['roles'] ?? []);

        return array_values(array_unique($roles));
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $data): array
    {
        if ($data === null) {
            return [];
        }

        Assert::that(value: $data)->isArray();

        /** @var array $data */

        $values = [];
        foreach ($data as $value) {
            Assert::that(value: $value)->string()->notBlank();
            /** @var non-empty-string $value */
            $values[] = $value;
        }

        return array_values(array: $values);
    }
}
