<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

use Assert\Assert;
use JsonSerializable;
use Stringable;

final readonly class KeycloakRequiredAction implements JsonSerializable, Stringable
{
    public const VERIFY_EMAIL = 'VERIFY_EMAIL';
    public const UPDATE_PROFILE = 'UPDATE_PROFILE';
    public const UPDATE_PASSWORD = 'UPDATE_PASSWORD';
    public const CONFIGURE_TOTP = 'CONFIGURE_TOTP';
    public const UPDATE_EMAIL = 'UPDATE_EMAIL';
    public const UPDATE_USER_LOCALE = 'UPDATE_USER_LOCALE';
    public const TERMS_AND_CONDITIONS = 'TERMS_AND_CONDITIONS';
    public const WEBAUTHN_REGISTER = 'WEBAUTHN_REGISTER';
    public const WEBAUTHN_REGISTER_PASSWORDLESS = 'WEBAUTHN_REGISTER_PASSWORDLESS';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        Assert::that(value: $value)->string()->notBlank();

        return new self($value);
    }

    public static function verifyEmail(): self
    {
        return new self(self::VERIFY_EMAIL);
    }

    public static function updateProfile(): self
    {
        return new self(self::UPDATE_PROFILE);
    }

    public static function updatePassword(): self
    {
        return new self(self::UPDATE_PASSWORD);
    }

    public static function configureTotp(): self
    {
        return new self(self::CONFIGURE_TOTP);
    }

    public static function updateEmail(): self
    {
        return new self(self::UPDATE_EMAIL);
    }

    public static function updateUserLocale(): self
    {
        return new self(self::UPDATE_USER_LOCALE);
    }

    public static function termsAndConditions(): self
    {
        return new self(self::TERMS_AND_CONDITIONS);
    }

    public static function webauthnRegister(): self
    {
        return new self(self::WEBAUTHN_REGISTER);
    }

    public static function webauthnRegisterPasswordless(): self
    {
        return new self(self::WEBAUTHN_REGISTER_PASSWORDLESS);
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
