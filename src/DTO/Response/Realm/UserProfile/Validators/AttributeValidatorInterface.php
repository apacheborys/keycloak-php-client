<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Response\Realm\UserProfile\Validators;

interface AttributeValidatorInterface
{
    public function getType(): string;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * @return array{type: string, config: array<string, mixed>}
     */
    public function toArray(): array;
}
