<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Date;

/**
 * @psalm-import-type JWT from JWTEncoder
 */
readonly class JWTDecoder
{
    public function __construct(private string $key, private ?string $issuer = null)
    {
    }

    public function decode(string $data): array
    {
        $components = explode(".", $data);
        if (count($components) !== 3) {
            throw new JWTException();
        }
        [$header, $payload, $signature] = $components;
        /** @var JWT $decoded */
        $decoded = json_decode(base64_decode($payload), true);
        $unsigned = sprintf("%s.%s", $header, $payload);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed) {
            throw new JWTException();
        }
        $date = new Date();
        if (array_key_exists("nbf", $decoded) && $decoded["nbf"] > $date->timeIntervalSinceReferenceDate) {
            throw new JWTException();
        }
        if (array_key_exists("exp", $decoded) && $decoded["exp"] < $date->timeIntervalSinceReferenceDate) {
            throw new JWTException();
        }
        if (array_key_exists("iss", $decoded) && $decoded["iss"] !== $this->issuer) {
            throw new JWTException();
        }
        return $decoded;
    }
}
