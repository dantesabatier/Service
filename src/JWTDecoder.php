<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Date;

readonly class JWTDecoder
{
    public function __construct(private string $key, private ?string $issuer = null)
    {
    }

    private function validate(string $token): bool
    {
        if (!str_contains($token, ".")) {
            return false;
        }
        $components = explode(".", $token);
        if (count($components) !== 3) {
            return false;
        }
        [$header, $payload, $signature] = $components;
        $unsigned = sprintf("%s.%s", $header, $payload);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed || !($obj = json_decode(base64_decode($payload)))) {
            return false;
        }
        $date = new Date();
        return !(((property_exists($obj, "nbf") && $obj->nbf > $date->timeIntervalSinceReferenceDate) || (property_exists($obj, "exp") && $obj->exp < $date->timeIntervalSinceReferenceDate) || (property_exists($obj, "iss") && $obj->iss !== $this->issuer)));
    }

    public function decode(string $data): ?array
    {
        if (!$this->validate($data)) {
            return null;
        }
        [, $payload,] = explode(".", $data);
        return json_decode(base64_decode($payload), true);
    }
}
