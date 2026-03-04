<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response;

use Assert\Assert;

final readonly class JwkDto
{
    /**
     * @param list<string> $x5c
     */
    public function __construct(
        private string $kty,
        private string $kid,
        private string $use,
        private string $alg,
        private string $n,
        private string $e,
        private array $x5c = [],
    ) {
        Assert::that($this->kty)->notBlank();
        Assert::that($this->kid)->notBlank();
        Assert::that($this->use)->notBlank();
        Assert::that($this->alg)->notBlank();
        Assert::that($this->n)->notBlank();
        Assert::that($this->e)->notBlank();

        foreach ($this->x5c as $certificate) {
            Assert::that($certificate)->string()->notBlank();
        }
    }

    public function getKty(): string
    {
        return $this->kty;
    }

    public function getKid(): string
    {
        return $this->kid;
    }

    public function getUse(): string
    {
        return $this->use;
    }

    public function getAlg(): string
    {
        return $this->alg;
    }

    public function getN(): string
    {
        return $this->n;
    }

    public function getE(): string
    {
        return $this->e;
    }

    /**
     * @return list<string>
     */
    public function getX5c(): array
    {
        return $this->x5c;
    }

    public function getFirstCertificate(): ?string
    {
        return $this->x5c[0] ?? null;
    }

    /**
     * @return array{
     *     kty: string,
     *     kid: string,
     *     use: string,
     *     alg: string,
     *     n: string,
     *     e: string,
     *     x5c: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'kty' => $this->kty,
            'kid' => $this->kid,
            'use' => $this->use,
            'alg' => $this->alg,
            'n' => $this->n,
            'e' => $this->e,
            'x5c' => $this->x5c,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('kty');
        Assert::that($data['kty'])->string()->notBlank();

        Assert::that($data)->keyExists('kid');
        Assert::that($data['kid'])->string()->notBlank();

        Assert::that($data)->keyExists('use');
        Assert::that($data['use'])->string()->notBlank();

        Assert::that($data)->keyExists('alg');
        Assert::that($data['alg'])->string()->notBlank();

        Assert::that($data)->keyExists('n');
        Assert::that($data['n'])->string()->notBlank();

        Assert::that($data)->keyExists('e');
        Assert::that($data['e'])->string()->notBlank();

        /** @var array{kty: string, kid: string, use: string, alg: string, n: string, e: string, x5c?: array<int, mixed>} $data */

        $x5cData = $data['x5c'] ?? [];
        Assert::that($x5cData)->isArray();

        /** @var array<int, mixed> $x5cData */

        $x5c = [];
        foreach ($x5cData as $certificate) {
            Assert::that($certificate)->string()->notBlank();
            /** @var string $certificate */
            $x5c[] = $certificate;
        }

        return new self(
            kty: $data['kty'],
            kid: $data['kid'],
            use: $data['use'],
            alg: $data['alg'],
            n: $data['n'],
            e: $data['e'],
            x5c: array_values($x5c),
        );
    }
}
