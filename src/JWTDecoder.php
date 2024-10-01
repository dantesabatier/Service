<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\Date;

readonly class JWTDecoder
{
    public function __construct(private string $key, private ?string $issuer = null)
    {
    }

    /**
     * @throws Exception
     */
    public function decode(string $data): array
    {
        $components = explode(".", $data);
        if (count($components) !== 3) {
            throw new JWTException();
        }
        [$header, $payload, $signature] = $components;
        if (!($decoded = json_decode(base64_decode($payload), true))) {
            throw new JWTException();
        }
        $unsigned = sprintf("%s.%s", $header, $payload);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed) {
            throw new JWTException();
        }
        $date = new Date();
        if (array_key_exists(JWTNotBeforeField, $decoded) && $decoded[JWTNotBeforeField] > $date->timeIntervalSinceReferenceDate) {
            throw new JWTException();
        }
        if (array_key_exists(JWTExpirationField, $decoded) && $decoded[JWTExpirationField] < $date->timeIntervalSinceReferenceDate) {
            throw new JWTException();
        }
        if (array_key_exists(JWTIssuerField, $decoded) && $decoded[JWTIssuerField] !== $this->issuer) {
            throw new JWTException();
        }
        return $decoded;
    }
}
