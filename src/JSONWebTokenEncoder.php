<?php

namespace Sabatier\Service;

use Exception;

readonly class JSONWebTokenEncoder
{
    public function __construct(private string $key)
    {
    }

    /**
     * @throws Exception
     */
    public function encode(JSONWebToken $token): string
    {
        $header = base64_encode(json_encode(["alg" => "HS256", "typ" => "JWT"], JSON_THROW_ON_ERROR));
        $encoded = base64_encode(json_encode($token, JSON_THROW_ON_ERROR));
        $unsigned = sprintf("%s.%s", $header, $encoded);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return sprintf("%s.%s", $unsigned, $signed);
    }
}
