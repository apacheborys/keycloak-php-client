<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Assert\Assert;

readonly final class CreateUserDto
{
    private string $username;

    private string $email;

    private string $firstName;

    private string $lastName;

    private string $realm;

    public function __construct(
        string $username,
        string $email,
        private bool $emailVerified,
        private bool $enabled,
        string $firstName,
        string $lastName,
        /**
         * @var CredentialsDto[]
         */
        private array $credentials,
        string $realm,
    ) {
        Assert::that(value: $username)->notEmpty();
        $this->username = $username;

        Assert::that(value: $email)->notEmpty()->email();
        $this->email = $email;

        Assert::that(value: $firstName)->notEmpty();
        $this->firstName = $firstName;

        Assert::that(value: $lastName)->notEmpty();
        $this->lastName = $lastName;

        Assert::that(value: $realm)->notEmpty();
        $this->realm = $realm;
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function toArray(): array
    {
        $result = [
            'username' => $this->username,
            'email' => $this->email,
            'emailVerified' => $this->emailVerified,
            'enabled' => $this->enabled,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
        ];

        if (!empty($this->credentials)) {
            $result['credentials'] = array_map(
                callback: static fn(CredentialsDto $credentials): array => $credentials->toArray(),
                array: $this->credentials
            );
        }

        return $result;
    }
}
