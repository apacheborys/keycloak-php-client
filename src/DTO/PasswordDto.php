<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO;

use Apacheborys\KeycloakPhpClient\ValueObject\HashAlgorithm;
use Assert\Assert;

final readonly class PasswordDto
{
    private ?string $plainPassword;

    private ?string $hashedPassword;

    private ?HashAlgorithm $hashAlgorithm;

    private ?int $hashIterations;

    private ?string $hashSalt;

    public function __construct(
        ?string $plainPassword = null,
        ?string $hashedPassword = null,
        ?HashAlgorithm $hashAlgorithm = null,
        ?int $hashIterations = null,
        ?string $hashSalt = null,
    ) {
        $atLeastOneShouldBeSetted = is_string($plainPassword) ||
                $this->isHashedPasswordFilled($hashedPassword, $hashAlgorithm, $hashIterations, $hashSalt);

        Assert::that($atLeastOneShouldBeSetted)->true();

        $this->plainPassword = $plainPassword;
        $this->hashedPassword = $hashedPassword;
        $this->hashAlgorithm = $hashAlgorithm;
        $this->hashIterations = $hashIterations;
        $this->hashSalt = $hashSalt;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function getHashedPassword(): ?string
    {
        return $this->hashedPassword;
    }

    public function getHashAlgorithm(): ?HashAlgorithm
    {
        return $this->hashAlgorithm;
    }

    public function getHashIterations(): ?int
    {
        return $this->hashIterations;
    }

    public function getHashSalt(): ?string
    {
        return $this->hashSalt;
    }

    private function isHashedPasswordFilled(
        ?string $hashedPassword = null,
        ?HashAlgorithm $hashAlgorithm = null,
        ?int $hashIterations = null,
        ?string $hashSalt = null,
    ): bool {
        return match ($hashAlgorithm) {
            HashAlgorithm::ARGON, HashAlgorithm::BCRYPT =>
                is_string($hashedPassword) && is_int($hashIterations) && is_string($hashSalt),
            HashAlgorithm::MD5 => is_string($hashedPassword),
            default => false,
        };
    }
}
