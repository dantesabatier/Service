<?php

namespace Sabatier\Service;

use Exception;

readonly class JWTEncoder
{
    public function __construct(private string $key)
    {
    }

    /**
     * @param array<string, mixed> $value
     * @return string
     * @throws Exception
     */
    public function encode(array $value): string
    {
        $header = base64_encode(json_encode([JWTAlgorithmHeaderKey => "HS256", JWTTypeHeaderKey => "JWT"], JSON_THROW_ON_ERROR));
        $encoded = base64_encode(json_encode($value, JSON_THROW_ON_ERROR));
        $unsigned = sprintf("%s.%s", $header, $encoded);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return sprintf("%s.%s", $unsigned, $signed);
    }
}
