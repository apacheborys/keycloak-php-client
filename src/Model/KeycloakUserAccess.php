<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Model;

use Assert\Assert;
use JsonSerializable;

final readonly class KeycloakUserAccess implements JsonSerializable
{
    public function __construct(
        private bool $manageGroupMembership,
        private bool $view,
        private bool $mapRoles,
        private bool $impersonate,
        private bool $manage,
    ) {
    }

    public function isManageGroupMembership(): bool
    {
        return $this->manageGroupMembership;
    }

    public function isView(): bool
    {
        return $this->view;
    }

    public function isMapRoles(): bool
    {
        return $this->mapRoles;    
    }

    public function isImpersonate(): bool
    {
        return $this->impersonate;
    }

    public function isManage(): bool
    {
        return $this->manage;
    }

    public function jsonSerialize(): array
    {
        return [
            'manageGroupMembership' => $this->manageGroupMembership,
            'view' => $this->view,
            'mapRoles' => $this->mapRoles,
            'impersonate' => $this->impersonate,
            'manage' => $this->manage,
        ];
    }

    /**
     * @param array<string, bool> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        Assert::that(value: $data)->keyExists(key: 'manageGroupMembership');
        Assert::that(value: $data['manageGroupMembership'])->boolean();

        Assert::that(value: $data)->keyExists(key: 'view');
        Assert::that(value: $data['view'])->boolean();

        Assert::that(value: $data)->keyExists(key: 'mapRoles');
        Assert::that(value: $data['mapRoles'])->boolean();

        Assert::that(value: $data)->keyExists(key: 'impersonate');
        Assert::that(value: $data['impersonate'])->boolean();

        Assert::that(value: $data)->keyExists(key: 'manage');
        Assert::that(value: $data['manage'])->boolean();

        return new self(
            manageGroupMembership: $data['manageGroupMembership'],
            view: $data['view'],
            mapRoles: $data['mapRoles'],
            impersonate: $data['impersonate'],
            manage: $data['manage'],
        );
    }
}
