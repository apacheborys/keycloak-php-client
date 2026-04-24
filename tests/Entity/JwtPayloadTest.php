<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Entity;

use Apacheborys\KeycloakPhpClient\Entity\JsonWebToken;
use Apacheborys\KeycloakPhpClient\Tests\Support\JwtTestFactory;
use PHPUnit\Framework\TestCase;

final class JwtPayloadTest extends TestCase
{
    public function testProvidesAccessToCustomClaimsByKey(): void
    {
        $token = JsonWebToken::fromRawToken(
            JwtTestFactory::buildJwtToken(
                payloadOverrides: [
                    'external_user_id' => 'some-external-user-id',
                    'name' => 'Oleg Petrenko',
                ],
            ),
        );
        $payload = $token->getPayload();

        self::assertTrue($payload->hasClaim('external_user_id'));
        self::assertSame('some-external-user-id', $payload->getClaim('external_user_id'));
        self::assertSame('Oleg Petrenko', $payload->getClaim('name'));
        self::assertNull($payload->getClaim('missing_claim'));
        self::assertArrayHasKey('external_user_id', $payload->getClaims());
    }

    public function testReturnsAdditionalClaimsThatAreNotMappedToDedicatedGetters(): void
    {
        $token = JsonWebToken::fromRawToken(
            JwtTestFactory::buildJwtToken(
                payloadOverrides: [
                    'external_user_id' => 'some-external-user-id',
                    'external_user_id_test' => 'some-external-user-id',
                    'name' => 'Oleg Petrenko',
                ],
            ),
        );

        self::assertSame(
            [
                'external_user_id' => 'some-external-user-id',
                'external_user_id_test' => 'some-external-user-id',
                'name' => 'Oleg Petrenko',
            ],
            $token->getPayload()->getAdditionalClaims(),
        );
    }
}
