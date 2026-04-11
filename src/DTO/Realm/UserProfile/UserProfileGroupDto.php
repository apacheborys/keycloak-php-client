<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile;

use Assert\Assert;

final readonly class UserProfileGroupDto
{
    public function __construct(
        private string $name,
        private ?string $displayHeader = null,
        private ?string $displayDescription = null,
    ) {
        Assert::that($this->name)->string()->notBlank();

        if ($this->displayHeader !== null) {
            Assert::that($this->displayHeader)->string()->notBlank();
        }

        if ($this->displayDescription !== null) {
            Assert::that($this->displayDescription)->string()->notBlank();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayHeader(): ?string
    {
        return $this->displayHeader;
    }

    public function getDisplayDescription(): ?string
    {
        return $this->displayDescription;
    }

    /**
     * @return array{
     *     name: string,
     *     displayHeader?: string,
     *     displayDescription?: string
     * }
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->displayHeader !== null) {
            $data['displayHeader'] = $this->displayHeader;
        }

        if ($this->displayDescription !== null) {
            $data['displayDescription'] = $this->displayDescription;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('name');
        Assert::that($data['name'])->string()->notBlank();

        /** @var array{
         *     name: string,
         *     displayHeader?: mixed,
         *     displayDescription?: mixed
         * } $data
         */

        if (array_key_exists('displayHeader', $data) && $data['displayHeader'] !== null) {
            Assert::that($data['displayHeader'])->string()->notBlank();
        }

        if (array_key_exists('displayDescription', $data) && $data['displayDescription'] !== null) {
            Assert::that($data['displayDescription'])->string()->notBlank();
        }

        return new self(
            name: $data['name'],
            displayHeader: is_string($data['displayHeader'] ?? null) ? $data['displayHeader'] : null,
            displayDescription: is_string($data['displayDescription'] ?? null) ? $data['displayDescription'] : null,
        );
    }
}
