<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile;

use Assert\Assert;

final readonly class UserProfileGroupDto
{
    public function __construct(
        private string $name,
        private ?string $displayHeader = null,
        private ?string $displayDescription = null,
        private array $annotations = [],
        private array $extra = [],
    ) {
        Assert::that($this->name)->string()->notBlank();

        if ($this->displayHeader !== null) {
            Assert::that($this->displayHeader)->string()->notBlank();
        }

        if ($this->displayDescription !== null) {
            Assert::that($this->displayDescription)->string()->notBlank();
        }

        foreach ($this->annotations as $key => $value) {
            Assert::that($key)->string()->notBlank();
            $_ = $value;
        }

        foreach ($this->extra as $key => $value) {
            Assert::that($key)->string()->notBlank();
            $_ = $value;
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->extra;
        $data['name'] = $this->name;

        if ($this->displayHeader !== null) {
            $data['displayHeader'] = $this->displayHeader;
        } else {
            unset($data['displayHeader']);
        }

        if ($this->displayDescription !== null) {
            $data['displayDescription'] = $this->displayDescription;
        } else {
            unset($data['displayDescription']);
        }

        $data['annotations'] = $this->annotations;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
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
         *     displayDescription?: mixed,
         *     annotations?: mixed
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
            annotations: self::normalizeAnnotations(data: $data['annotations'] ?? []),
            extra: self::extractExtra(data: $data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeAnnotations(mixed $data): array
    {
        Assert::that($data)->isArray();
        /** @var array<int|string, mixed> $data */

        $annotations = [];
        foreach ($data as $key => $value) {
            Assert::that($key)->string()->notBlank();
            /** @var string $key */
            $annotations[$key] = $value;
        }

        return $annotations;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractExtra(array $data): array
    {
        unset(
            $data['name'],
            $data['displayHeader'],
            $data['displayDescription'],
            $data['annotations'],
        );

        return $data;
    }
}
