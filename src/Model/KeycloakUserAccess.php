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

    #[\Override]
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
        Assert::that($data)->keyExists('manageGroupMembership');
        Assert::that($data['manageGroupMembership'])->boolean();

        Assert::that($data)->keyExists('view');
        Assert::that($data['view'])->boolean();

        Assert::that($data)->keyExists('mapRoles');
        Assert::that($data['mapRoles'])->boolean();

        Assert::that($data)->keyExists('impersonate');
        Assert::that($data['impersonate'])->boolean();

        Assert::that($data)->keyExists('manage');
        Assert::that($data['manage'])->boolean();

        return new self(
            manageGroupMembership: $data['manageGroupMembership'],
            view: $data['view'],
            mapRoles: $data['mapRoles'],
            impersonate: $data['impersonate'],
            manage: $data['manage'],
        );
    }
}
