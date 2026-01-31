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
        Assert::that($data)->keyExists('alg');
        Assert::that($data['alg'])->string();

        Assert::that($data)->keyExists('typ');
        Assert::that($data['typ'])->string()->eq('JWT');

        Assert::that($data)->keyExists('kid');
        Assert::that($data['kid'])->string();

        return new self(
            alg: $data['alg'],
            typ: $data['typ'],
            kid: $data['kid'],
        );
    }
}
