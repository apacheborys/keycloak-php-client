<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\DTO\RoleDto;
use Assert\Assert;

readonly final class CreateUserProfileDto
{
    /**
     * @param list<RoleDto> $roles
     * @param array<string, string|list<string>> $attributes
     */
    public function __construct(
        private string $username,
        private string $email,
        private bool $emailVerified,
        private bool $enabled,
        private string $firstName,
        private string $lastName,
        private string $realm,
        private array $roles = [],
        private array $attributes = [],
    ) {
        Assert::that($username)->notEmpty();
        Assert::that($email)->notEmpty()->email();
        Assert::that($firstName)->notEmpty();
        Assert::that($lastName)->notEmpty();
        Assert::that($realm)->notEmpty();

        foreach ($this->roles as $role) {
            Assert::that($role)->isInstanceOf(RoleDto::class);
        }

        self::assertAttributesMap(attributes: $this->attributes);
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getAttributes(): array
    {
        return self::normalizeAttributesMap(attributes: $this->attributes);
    }

    /**
     * @return list<RoleDto>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return array{
     *     username: string,
     *     email: string,
     *     emailVerified: bool,
     *     enabled: bool,
     *     firstName: string,
     *     lastName: string,
     *     attributes?: array<string, list<string>>
     * }
     */
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

        $attributes = $this->getAttributes();
        if ($attributes !== []) {
            $result['attributes'] = $attributes;
        }

        return $result;
    }

    /**
     * @param array<string, string|list<string>> $attributes
     */
    private static function assertAttributesMap(array $attributes): void
    {
        foreach ($attributes as $attributeName => $attributeValue) {
            Assert::that($attributeName)->string()->notEmpty();

            if (is_string($attributeValue)) {
                continue;
            }

            Assert::that($attributeValue)->isArray();
            foreach ($attributeValue as $singleValue) {
                Assert::that($singleValue)->string();
            }
        }
    }

    /**
     * @param array<string, string|list<string>> $attributes
     * @return array<string, list<string>>
     */
    private static function normalizeAttributesMap(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $attributeName => $attributeValue) {
            if (is_string($attributeValue)) {
                $normalized[$attributeName] = [$attributeValue];
                continue;
            }

            /** @var list<string> $attributeValue */
            $normalized[$attributeName] = array_values($attributeValue);
        }

        return $normalized;
    }
}
