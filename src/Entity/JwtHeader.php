<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;

final readonly class JwtHeader
{
    public function __construct(
        /**
         * Signature or encryption algorithm
         */
        private string $alg,
        /**
         * Type of token
         */
        private string $typ,
        /**
         * Key id
         */
        private string $kid,
    ) {
    }

    public function getAlg(): string
    {
        return $this->alg;
    }

    public function getTyp(): string
    {
        return $this->typ;
    }

    public function getKid(): string
    {
        return $this->kid;
    }

    public static function fromArray(array $data): self
    {
        Assert::that(value: $data)->keyExists(key: 'alg');
        Assert::that(value: $data['alg'])->string();

        Assert::that(value: $data)->keyExists(key: 'typ');
        Assert::that(value: $data['typ'])->string()->eq(value2: 'JWT');

        Assert::that(value: $data)->keyExists(key: 'kid');
        Assert::that(value: $data['kid'])->string();

        return new self(
            alg: $data['alg'],
            typ: $data['typ'],
            kid: $data['kid'],
        );
    }
}
