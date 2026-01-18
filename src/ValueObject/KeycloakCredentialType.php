<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

use Assert\Assert;
use JsonSerializable;
use Stringable;

final readonly class KeycloakCredentialType implements JsonSerializable, Stringable
{
    public const PASSWORD = 'password';
    public const OTP = 'otp';
    public const WEBAUTHN = 'webauthn';
    public const WEBAUTHN_PASSWORDLESS = 'webauthn-passwordless';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        Assert::that(value: $value)->string()->notBlank();

        return new self($value);
    }

    public static function password(): self
    {
        return new self(self::PASSWORD);
    }

    public static function otp(): self
    {
        return new self(self::OTP);
    }

    public static function webauthn(): self
    {
        return new self(self::WEBAUTHN);
    }

    public static function webauthnPasswordless(): self
    {
        return new self(self::WEBAUTHN_PASSWORDLESS);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
