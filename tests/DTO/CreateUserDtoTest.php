<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\DTO;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserProfileDto;
use Apacheborys\KeycloakPhpClient\Model\KeycloakCredential;
use Apacheborys\KeycloakPhpClient\ValueObject\KeycloakCredentialType;
use PHPUnit\Framework\TestCase;

final class CreateUserDtoTest extends TestCase
{
    public function testToArrayIncludesCredentials(): void
    {
        $profile = new CreateUserProfileDto(
            username: 'user@example.com',
            email: 'user@example.com',
            emailVerified: false,
            enabled: true,
            firstName: 'User',
            lastName: 'Example',
            realm: 'master',
        );

        $credential = new KeycloakCredential(
            type: KeycloakCredentialType::password(),
            credentialData: '{"algorithm":"bcrypt","hashIterations":10}',
            secretData: '{"value":"hash","salt":"salt"}',
            temporary: false,
        );

        $dto = new CreateUserDto(profile: $profile, credentials: [$credential]);

        self::assertSame('master', $dto->getProfile()->getRealm());
        self::assertSame('user@example.com', $dto->getProfile()->getEmail());
        self::assertSame(
            [
                'username' => 'user@example.com',
                'email' => 'user@example.com',
                'emailVerified' => false,
                'enabled' => true,
                'firstName' => 'User',
                'lastName' => 'Example',
                'credentials' => [
                    [
                        'type' => 'password',
                        'temporary' => false,
                        'credentialsData' => '{"algorithm":"bcrypt","hashIterations":10}',
                        'secretData' => '{"value":"hash","salt":"salt"}',
                    ],
                ],
            ],
            $dto->toArray(),
        );
    }
}
