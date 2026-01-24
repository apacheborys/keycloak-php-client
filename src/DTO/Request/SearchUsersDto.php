<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

readonly final class SearchUsersDto
{
    /**
     * @param array<string, string> $customAttributes
     */
    public function __construct(
        private string $realm,
        private ?string $search = null,
        private ?string $username = null,
        private ?string $email = null,
        private array $customAttributes = [],
        private int $first = 0,
        private int $max = 20,
        private bool $exact = false
    ) {
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    /**
     * @return array<string, int|string>
     */
    public function getQueryParameters(): array
    {
        $result = [];

        if (is_string(value: $this->search)) {
            $result['search'] = $this->search;
        }

        if (is_string(value: $this->username)) {
            $result['username'] = $this->username;
        }

        if (is_string(value: $this->email)) {
            $result['email'] = $this->email;
        }

        $result['first'] = $this->first;
        $result['max'] = $this->max;
        $result['exact'] = $this->exact ? 'true' : 'false';

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getCustomAttributes(): array
    {
        $attributes = [];
        foreach ($this->customAttributes as $attributeName => $customAttribute) {
            $attributes[(string) $attributeName] = (string) $customAttribute;
        }

        return $attributes;
    }
}
