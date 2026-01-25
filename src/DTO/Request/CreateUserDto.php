<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Request;

use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;

readonly final class CreateUserDto
{
    public function __construct(
        private CreateUserProfileDto $profile,
        /**
         * @var list<KeycloakCredential>
         */
        private array $credentials = [],
    ) {
    }

    public function getProfile(): CreateUserProfileDto
    {
        return $this->profile;
    }

    public function toArray(): array
    {
        $result = $this->profile->toArray();

        if (!empty($this->credentials)) {
            $result['credentials'] = array_map(
                callback: static fn(KeycloakCredential $credentials): array => $credentials->jsonSerialize(),
                array: $this->credentials
            );
        }

        return $result;
    }
}
