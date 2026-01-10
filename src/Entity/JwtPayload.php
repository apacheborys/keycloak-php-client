<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class JwtPayload
{
    /**
     * Expiration time (seconds since Unix epoch)
     */
    private DateTimeImmutable $exp;

    /**
     * Issued at (seconds since Unix epoch)
     */
    private DateTimeImmutable $iat;

    /**
     * JWT id (unique identifier for this token)
     */
    private UuidInterface $jti;

    /**
     * Issuer (who created and signed this token)
     */
    private string $iss;

    /**
     * Audience (who or what the token is intended for)
     */
    private string $aud;

    /**
     * Subject (whom the token refers to)
     */
    private UuidInterface $sub;

    /**
     * Type of token
     */
    private string $typ;

    /**
     * Authorized party (the party to which this token was issued)
     */
    private string $azp;

    /**
     * Authentication context class
     */
    private int $acr;

    /**
     * @var array{roles:string[]}
     */
    private array $realmAccess;

    /**
     * @var array{backend:array{roles:string[]}, account:array{roles:string[]}}
     */
    private array $resourceAccess;

    private string $scope;

    private bool $emailVerified;

    private string $clientHost;

    private string $preferredUsername;

    private string $clientAddress;

    private string $clientId;

    public function getExp(): DateTimeImmutable
    {
        return $this->exp;
    }

    public function getIat(): DateTimeImmutable
    {
        return $this->iat;
    }

    public function getJti(): UuidInterface
    {
        return $this->jti;
    }

    public function getIss(): string
    {
        return $this->iss;
    }

    public function getAud(): string
    {
        return $this->aud;
    }

    public function getSub(): UuidInterface
    {
        return $this->sub;
    }

    public function getTyp(): string
    {
        return $this->typ;
    }

    public function getAzp(): string
    {
        return $this->azp;
    }

    public function getAcr(): int
    {
        return $this->acr;
    }

    /**
     * @return array{roles:string[]}
     */
    public function getRealmAccess(): array
    {
        return $this->realmAccess;
    }

    /**
     * @return array{backend:array{roles:string[]}, account:array{roles:string[]}}
     */
    public function getResourceAccess(): array
    {
        return $this->resourceAccess;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getClientHost(): string
    {
        return $this->clientHost;
    }

    public function getPreferredUsername(): string
    {
        return $this->preferredUsername;
    }

    public function getClientAddress(): string
    {
        return $this->clientAddress;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public static function fromArray(array $data): self
    {
        $payload = new self();

        Assert::that(value: $data)->keyExists(key: 'exp');
        Assert::that(value: $data['exp'])->notEmpty()->integer();
        $payload->exp = new DateTimeImmutable(datetime: '@' . $data['exp']);

        Assert::that(value: $data)->keyExists(key: 'iat');
        Assert::that(value: $data['iat'])->notEmpty()->integer();
        $payload->iat = new DateTimeImmutable(datetime: '@' . $data['iat']);

        Assert::that(value: $data)->keyExists(key: 'jti');
        Assert::that(value: Uuid::isValid(uuid: $data['jti']))->true();
        $payload->jti = Uuid::fromString(uuid: $data['jti']);

        Assert::that(value: $data)->keyExists(key: 'iss');
        Assert::that(value: $data['iss'])->string()->notEmpty()->url();
        $payload->iss = $data['iss'];

        Assert::that(value: $data)->keyExists(key: 'aud');
        Assert::that(value: $data['aud'])->string()->notEmpty();
        $payload->aud = $data['aud'];

        Assert::that(value: $data)->keyExists(key: 'sub');
        Assert::that(value: Uuid::isValid(uuid: $data['sub']))->true();
        $payload->sub = Uuid::fromString(uuid: $data['sub']);

        Assert::that(value: $data)->keyExists(key: 'typ');
        Assert::that(value: $data['typ'])->string()->notEmpty();
        $payload->typ = $data['typ'];

        Assert::that(value: $data)->keyExists(key: 'azp');
        Assert::that(value: $data['azp'])->string()->notEmpty();
        $payload->azp = $data['azp'];

        Assert::that(value: $data)->keyExists(key: 'acr');
        Assert::that(value: $data['acr'])->numeric()->notEmpty();
        $payload->acr = (int) $data['acr'];

        Assert::that(value: $data)->keyExists(key: 'realm_access');
        Assert::that(value: $data['realm_access'])->isArray()->keyExists(key: 'roles');
        Assert::that(value: $data['realm_access']['roles'])->isArray();
        $payload->realmAccess = $data['realm_access'];

        Assert::that(value: $data)->keyExists(key: 'resource_access');
        Assert::that(value: $data['resource_access'])->isArray()->keyExists(key: 'backend');
        Assert::that(value: $data['resource_access']['backend'])->isArray()->keyExists(key: 'roles');
        Assert::that(value: $data['resource_access']['backend']['roles'])->isArray();

        Assert::that(value: $data['resource_access'])->isArray()->keyExists(key: 'account');
        Assert::that(value: $data['resource_access']['account'])->isArray()->keyExists(key: 'roles');
        Assert::that(value: $data['resource_access']['account']['roles'])->isArray();
        $payload->resourceAccess = $data['resource_access'];

        Assert::that(value: $data)->keyExists(key: 'scope');
        Assert::that(value: $data['scope'])->string();
        $payload->scope = $data['scope'];
    
        Assert::that(value: $data)->keyExists(key: 'email_verified');
        Assert::that(value: $data['email_verified'])->boolean();
        $payload->emailVerified = $data['email_verified'];

        Assert::that(value: $data)->keyExists(key: 'clientHost');
        Assert::that(value: $data['clientHost'])->string()->ip();
        $payload->clientHost = $data['clientHost'];

        Assert::that(value: $data)->keyExists(key: 'preferred_username');
        Assert::that(value: $data['preferred_username'])->string()->notBlank();
        $payload->preferredUsername = $data['preferred_username'];

        Assert::that(value: $data)->keyExists(key: 'clientAddress');
        Assert::that(value: $data['clientAddress'])->string()->ip();
        $payload->clientAddress = $data['clientAddress'];

        Assert::that(value: $data)->keyExists(key: 'client_id');
        Assert::that(value: $data['client_id'])->string()->notBlank();
        $payload->clientId = $data['client_id'];

        return $payload;
    }
}
