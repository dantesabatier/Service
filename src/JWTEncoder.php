<?php

namespace Sabatier\Service;

use Exception;

readonly class JWTEncoder
{
    public function __construct(private string $key)
    {
    }
    
    public function encode(array $value): string
    {
        $header = base64_encode(json_encode(["alg" => "HS256", "typ" => "JWT"]));
        $value = base64_encode(json_encode($value));
        $unsigned = sprintf("%s.%s", $header, $value);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return sprintf("%s.%s", $unsigned, $signed);
    }
}
