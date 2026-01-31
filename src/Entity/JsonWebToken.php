<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Entity;

use Assert\Assert;
use JsonSerializable;
use Override;

final readonly class JsonWebToken implements JsonSerializable
{
    public function __construct(
        private string $rawToken,
        private JwtHeader $header,
        private JwtPayload $payload,
        private string $signature,
    ) {
    }

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

    public static function fromRawToken(string $rawToken): self
    {
        Assert::that($rawToken)->notBlank();

        /**
         * @var string[] $parts
         */
        $parts = explode(separator: '.', string: $rawToken);

        Assert::that($parts)->isArray()->count(3);

        foreach ($parts as $key => $part) {
            Assert::that($key)->integer()->between(0, 2);
            Assert::that($part)->string();
        }

        $headerJson = self::decodePart(part: $parts[0]);

        $decodedHeader = json_decode(json: $headerJson, associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::that($decodedHeader)->isArray();

        /** @var array $decodedHeader */

        $payloadJson = self::decodePart(part: $parts[1]);

        $decodedPayload = json_decode(json: $payloadJson, associative: true, flags: JSON_THROW_ON_ERROR);
        Assert::that($decodedPayload)->isArray();

        /** @var array $decodedPayload */

        return new self(
            rawToken: $rawToken,
            header: JwtHeader::fromArray(data: $decodedHeader),
            payload: JwtPayload::fromArray(data: $decodedPayload),
            signature: $parts[2],
        );
    }

    private static function decodePart(string $part): string
    {
        $remainder = strlen(string: $part) % 4;
        if ($remainder !== 0) {
            $part .= str_repeat(string: '=', times: 4 - $remainder);
        }

        $decoded = base64_decode(string: strtr(string: $part, from: '-_', to: '+/'), strict: true);
        Assert::that($decoded)->string();

        /** @var string $decoded */
        return $decoded;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'token' => $this->rawToken,
        ];
    }
}
