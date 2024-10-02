<?php

namespace Sabatier\Service;

use Exception;

/**
 * @psalm-type JWT = array{iss: string|null, sub?: string, aud?: string, exp: float, nbf?: float, iat: float, jti: string, dat?: mixed|null}
 */
readonly class JWTEncoder
{
    public function __construct(private string $key)
    {
    }

    /**
     * @param JWT $value
     * @return string
     * @throws Exception
     */
    public function encode(array $value): string
    {
        $header = base64_encode(json_encode(["alg" => "HS256", "typ" => "JWT"], JSON_THROW_ON_ERROR));
        $encoded = base64_encode(json_encode($value, JSON_THROW_ON_ERROR));
        $unsigned = sprintf("%s.%s", $header, $encoded);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return sprintf("%s.%s", $unsigned, $signed);
    }
}
