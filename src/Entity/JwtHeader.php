<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;

final readonly class JwtHeader
{
    /**
     * Signature or encryption algorithm
     */
    private string $alg;

    /**
     * Type of token
     */
    private string $typ;

    /**
     * Key id
     */
    private string $kid;

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
        $header = new self();

        Assert::that(value: $data)->keyExists(key: 'alg');
        Assert::that(value: $data['alg'])->string();
        $header->alg = $data['alg'];

        Assert::that(value: $data)->keyExists(key: 'typ');
        Assert::that(value: $data['typ'])->string()->eq(value2: 'JWT');
        $header->typ = $data['typ'];

        Assert::that(value: $data)->keyExists(key: 'kid');
        Assert::that(value: $data['kid'])->string();
        $header->kid = $data['kid'];

        return $header;
    }
}
