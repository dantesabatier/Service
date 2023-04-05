<?php

namespace Sabatier\Service;

use Exception;

readonly class JWTEncoder
{
    public function __construct(private string $key)
    {
    }

    /**
     * @throws Exception
     */
    public function encode(array $value): string
    {
        $header = base64_encode(json_encode(["alg" => "HS256", "typ" => "JWT"]));
        $value = base64_encode(json_encode($value, JSON_THROW_ON_ERROR));
        $unsigned = sprintf("%s.%s", $header, $value);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return sprintf("%s.%s", $unsigned, $signed);
    }
}
