<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Http\Internal;

use Apacheborys\KeycloakPhpClient\Http\Internal\KeycloakErrorResponseParser;
use PHPUnit\Framework\TestCase;

final class KeycloakErrorResponseParserTest extends TestCase
{
    public function testParserExtractsOauthErrorAndDescription(): void
    {
        $body = json_encode(
            [
                'error' => 'invalid_grant',
                'error_description' => 'Invalid user credentials',
            ],
            JSON_THROW_ON_ERROR,
        );

        $parsedResponse = KeycloakErrorResponseParser::parse($body);

        self::assertSame($body, $parsedResponse->getBody());
        self::assertSame('invalid_grant', $parsedResponse->getError());
        self::assertSame(
            'Invalid user credentials',
            $parsedResponse->getErrorDescription(),
        );
    }

    public function testParserExtractsErrorMessage(): void
    {
        $body = json_encode(
            [
                'errorMessage' => 'User exists with same username',
            ],
            JSON_THROW_ON_ERROR,
        );

        $parsedResponse = KeycloakErrorResponseParser::parse($body);

        self::assertSame($body, $parsedResponse->getBody());
        self::assertNull($parsedResponse->getError());
        self::assertSame(
            'User exists with same username',
            $parsedResponse->getErrorDescription(),
        );
    }

    public function testParserHandlesInvalidJsonWithoutThrowing(): void
    {
        $body = '{"error":"invalid_grant"';

        $parsedResponse = KeycloakErrorResponseParser::parse($body);

        self::assertSame($body, $parsedResponse->getBody());
        self::assertNull($parsedResponse->getError());
        self::assertNull($parsedResponse->getErrorDescription());
    }

    public function testParserHandlesValidJsonThatIsNotAnObject(): void
    {
        $body = json_encode(['invalid_grant'], JSON_THROW_ON_ERROR);

        $parsedResponse = KeycloakErrorResponseParser::parse($body);

        self::assertSame($body, $parsedResponse->getBody());
        self::assertNull($parsedResponse->getError());
        self::assertNull($parsedResponse->getErrorDescription());
    }

    public function testParserHandlesEmptyBody(): void
    {
        $parsedResponse = KeycloakErrorResponseParser::parse('');

        self::assertSame('', $parsedResponse->getBody());
        self::assertNull($parsedResponse->getError());
        self::assertNull($parsedResponse->getErrorDescription());
    }
}
