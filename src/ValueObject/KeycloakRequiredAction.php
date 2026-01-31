<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\ValueObject;

use Assert\Assert;
use JsonSerializable;
use Override;
use Stringable;

final readonly class KeycloakRequiredAction implements JsonSerializable, Stringable
{
    public const string VERIFY_EMAIL = 'VERIFY_EMAIL';
    public const string UPDATE_PROFILE = 'UPDATE_PROFILE';
    public const string UPDATE_PASSWORD = 'UPDATE_PASSWORD';
    public const string CONFIGURE_TOTP = 'CONFIGURE_TOTP';
    public const string UPDATE_EMAIL = 'UPDATE_EMAIL';
    public const string UPDATE_USER_LOCALE = 'UPDATE_USER_LOCALE';
    public const string TERMS_AND_CONDITIONS = 'TERMS_AND_CONDITIONS';
    public const string WEBAUTHN_REGISTER = 'WEBAUTHN_REGISTER';
    public const string WEBAUTHN_REGISTER_PASSWORDLESS = 'WEBAUTHN_REGISTER_PASSWORDLESS';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        Assert::that($value)->string()->notBlank();

        return new self(value: $value);
    }

    public static function verifyEmail(): self
    {
        return new self(value: self::VERIFY_EMAIL);
    }

    public static function updateProfile(): self
    {
        return new self(value: self::UPDATE_PROFILE);
    }

    public static function updatePassword(): self
    {
        return new self(value: self::UPDATE_PASSWORD);
    }

    public static function configureTotp(): self
    {
        return new self(value: self::CONFIGURE_TOTP);
    }

    public static function updateEmail(): self
    {
        return new self(self::UPDATE_EMAIL);
    }

    public static function updateUserLocale(): self
    {
        return new self(value: self::UPDATE_USER_LOCALE);
    }

    public static function termsAndConditions(): self
    {
        return new self(value: self::TERMS_AND_CONDITIONS);
    }

    public static function webauthnRegister(): self
    {
        return new self(value: self::WEBAUTHN_REGISTER);
    }

    public static function webauthnRegisterPasswordless(): self
    {
        return new self(value: self::WEBAUTHN_REGISTER_PASSWORDLESS);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
