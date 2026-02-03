<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class JwtPayload
{
    public function __construct(
        /**
         * Expiration time (seconds since Unix epoch)
         */
        private DateTimeImmutable $exp,
        /**
         * Issued at (seconds since Unix epoch)
         */
        private DateTimeImmutable $iat,
        /**
         * JWT id (unique identifier for this token)
         */
        private UuidInterface $jti,
        /**
         * Issuer (who created and signed this token)
         */
        private string $iss,
        /**
         * Audience (who or what the token is intended for)
         * @var string[]
         */
        private array $aud,
        /**
         * Subject (whom the token refers to)
         */
        private UuidInterface $sub,
        /**
         * Type of token
         */
        private string $typ,
        /**
         * Authorized party (the party to which this token was issued)
         */
        private string $azp,
        /**
         * Authentication context class
         */
        private int $acr,
        /**
         * @var array{roles:string[]}
         */
        private array $realmAccess,
        /**
         * @var array{backend:array{roles:string[]}, account:array{roles:string[]}}
         */
        private array $resourceAccess,
        private string $scope,
        private bool $emailVerified,
        private string $clientHost,
        private string $preferredUsername,
        private string $clientAddress,
        private string $clientId
    ) {
    }

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

    /**
     * @return string[]
     */
    public function getAud(): array
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
        Assert::that($data)->keyExists('exp');
        Assert::that($data['exp'])->notEmpty()->integer();
        $exp = new DateTimeImmutable(datetime: '@' . $data['exp']);

        Assert::that($data)->keyExists('iat');
        Assert::that($data['iat'])->notEmpty()->integer();
        $iat = new DateTimeImmutable(datetime: '@' . $data['iat']);

        Assert::that($data)->keyExists('jti');
        Assert::that(Uuid::isValid($data['jti']))->true();
        $jti = Uuid::fromString(uuid: $data['jti']);

        Assert::that($data)->keyExists('iss');
        Assert::that($data['iss'])->string()->notEmpty()->url();
        $iss = $data['iss'];

        Assert::that($data)->keyExists('aud');
        Assert::that($data['aud'])->isArray()->notEmpty();
        $aud = $data['aud'];

        Assert::that($data)->keyExists('sub');
        Assert::that(Uuid::isValid($data['sub']))->true();
        $sub = Uuid::fromString(uuid: $data['sub']);

        Assert::that($data)->keyExists('typ');
        Assert::that($data['typ'])->string()->notEmpty();
        $typ = $data['typ'];

        Assert::that($data)->keyExists('azp');
        Assert::that($data['azp'])->string()->notEmpty();
        $azp = $data['azp'];

        Assert::that($data)->keyExists('acr');
        Assert::that($data['acr'])->numeric()->notEmpty();
        $acr = (int) $data['acr'];

        Assert::that($data)->keyExists('realm_access');
        Assert::that($data['realm_access'])->isArray()->keyExists('roles');
        Assert::that($data['realm_access']['roles'])->isArray();
        $realmAccess = $data['realm_access'];

        Assert::that($data)->keyExists('resource_access');
        Assert::that($data['resource_access'])->isArray()->keyExists('backend');
        Assert::that($data['resource_access']['backend'])->isArray()->keyExists('roles');
        Assert::that($data['resource_access']['backend']['roles'])->isArray();

        Assert::that($data['resource_access'])->isArray()->keyExists('account');
        Assert::that($data['resource_access']['account'])->isArray()->keyExists('roles');
        Assert::that($data['resource_access']['account']['roles'])->isArray();
        $resourceAccess = $data['resource_access'];

        Assert::that($data)->keyExists('scope');
        Assert::that($data['scope'])->string();
        $scope = $data['scope'];

        Assert::that($data)->keyExists('email_verified');
        Assert::that($data['email_verified'])->boolean();
        $emailVerified = $data['email_verified'];

        Assert::that($data)->keyExists('clientHost');
        Assert::that($data['clientHost'])->string()->ip();
        $clientHost = $data['clientHost'];

        Assert::that($data)->keyExists('preferred_username');
        Assert::that($data['preferred_username'])->string()->notBlank();
        $preferredUsername = $data['preferred_username'];

        Assert::that($data)->keyExists('clientAddress');
        Assert::that($data['clientAddress'])->string()->ip();
        $clientAddress = $data['clientAddress'];

        Assert::that($data)->keyExists('client_id');
        Assert::that($data['client_id'])->string()->notBlank();
        $clientId = $data['client_id'];

        return new self(
            exp: $exp,
            iat: $iat,
            jti: $jti,
            iss: $iss,
            aud: $aud,
            sub: $sub,
            typ: $typ,
            azp: $azp,
            acr: $acr,
            realmAccess: $realmAccess,
            resourceAccess: $resourceAccess,
            scope: $scope,
            emailVerified: $emailVerified,
            clientHost: $clientHost,
            preferredUsername: $preferredUsername,
            clientAddress: $clientAddress,
            clientId: $clientId,
        );
    }
}
