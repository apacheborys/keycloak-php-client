<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class KeycloakRealm implements JsonSerializable
{
    public function __construct(
        private string $name,
        private ?UuidInterface $id,
        private ?string $displayName,
        private ?string $displayNameHtml,
        private ?bool $enabled,
    ) {
    }

    public static function fromArray(array $data): self
    {
        Assert::that($data)->keyExists('realm');
        Assert::that($data['realm'])->string()->notBlank();

        $id = null;
        if (array_key_exists(key: 'id', array: $data)) {
            Assert::that(Uuid::isValid($data['id']))->true();
            $id = Uuid::fromString(uuid: $data['id']);
        }

        $displayName = null;
        if (array_key_exists(key: 'displayName', array: $data)) {
            Assert::that($data['displayName'])->string();
            $displayName = $data['displayName'];
        }

        $displayNameHtml = null;
        if (array_key_exists(key: 'displayNameHtml', array: $data)) {
            Assert::that($data['displayNameHtml'])->string();
            $displayNameHtml = $data['displayNameHtml'];
        }

        $enabled = null;
        if (array_key_exists(key: 'enabled', array: $data)) {
            Assert::that($data['enabled'])->boolean();
            $enabled = $data['enabled'];
        }

        /**
         * @var array{realm: non-empty-string} $data
         */

        return new self(
            name: $data['realm'],
            id: $id,
            displayName: $displayName,
            displayNameHtml: $displayNameHtml,
            enabled: $enabled,
        );
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        $result = [
            'realm' => $this->name,
        ];

        if (!is_null(value: $this->id)) {
            $result['id'] = $this->id->toString();
        }

        if (!is_null(value: $this->displayName)) {
            $result['displayName'] = $this->displayName;
        }

        if (!is_null(value: $this->displayNameHtml)) {
            $result['displayNameHtml'] = $this->displayNameHtml;
        }

        if (!is_null(value: $this->enabled)) {
            $result['enabled'] = $this->enabled;
        }

        return $result;
    }
}
