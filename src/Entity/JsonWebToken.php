<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;
use DateTimeImmutable;
use JsonSerializable;
use Ramsey\Uuid\UuidInterface;

final readonly class JsonWebToken implements JsonSerializable
{
    private string $rawToken;

    private JwtHeader $header;

    private JwtPayload $payload;

    private string $signature;

    public function getRawToken(): string
    {
        return $this->rawToken;
    }

    public function getHeader(): JwtHeader
    {
        return $this->header;    
    }

    public function getPayload(): JwtPayload
    {
        return $this->payload;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public static function fromArray(string $rawToken): self
    {
        $jwt = new self();

        Assert::that(value: $rawToken)->notBlank();
        $jwt->rawToken = $rawToken;

        /**
         * @var $parts string[]
         */
        $parts = explode(separator: '.', string: $rawToken);

        Assert::that(value: $parts)->isArray()->count(count: 3);
        foreach ($parts as $key => $part) {
            Assert::that(value: $key)->integer()->between(lowerLimit: 0, upperLimit: 2);
            Assert::that(value: $part)->string();
        }

        $headerJson = self::decodePart(part: $parts[0]);

        $decodedHeader = json_decode(json: $headerJson, associative: true);
        Assert::that(value: $decodedHeader)->isArray();

        $jwt->header = JwtHeader::fromArray(data: $decodedHeader);

        $payloadJson = self::decodePart(part: $parts[1]);

        $decodedPayload = json_decode(json: $payloadJson, associative: true);
        Assert::that(value: $decodedPayload)->isArray();

        $jwt->payload = JwtPayload::fromArray(data: $decodedPayload);

        $jwt->signature = $parts[2];

        return $jwt;
    }

    private static function decodePart(string $part): string
    {
        $remainder = strlen($part) % 4;
        if ($remainder !== 0) {
            $part .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(string: strtr($part, '-_', '+/'), strict: true);
        Assert::that(value: $decoded)->string();

        return $decoded;
    }

    public function jsonSerialize(): array
    {
        return [
            'token' => $this->rawToken,
        ];
    }
}
