<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request\User;

use Assert\Assert;
use Ramsey\Uuid\UuidInterface;

readonly final class CreateUserProfileDto
{
    /**
     * @var list<AttributeValueDto>
     */
    private array $attributes;

    /**
     * @param list<AttributeValueDto>|array<string, int|string|UuidInterface|list<string>> $attributes
     */
    public function __construct(
        private string $username,
        private string $email,
        private bool $emailVerified,
        private bool $enabled,
        private string $firstName,
        private string $lastName,
        private string $realm,
        array $attributes = [],
    ) {
        Assert::that($username)->notEmpty();
        Assert::that($email)->notEmpty()->email();
        Assert::that($firstName)->notEmpty();
        Assert::that($lastName)->notEmpty();
        Assert::that($realm)->notEmpty();

        $this->attributes = AttributeValueDto::normalizeCollection(attributes: $attributes);
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
     * @return list<AttributeValueDto>
     */
    public function getAttributeDtos(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getAttributes(): array
    {
        return AttributeValueDto::normalizeCollectionToMap(attributes: $this->attributes);
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
}
