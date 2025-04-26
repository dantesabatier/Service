<?php

namespace Sabatier\Service;

use Exception;

/**
 * This class provides functionality to encode a JSON Web Token (JWT) using the HS256 algorithm. The generated token includes a header, payload, and signature.
 */
readonly class JSONWebTokenEncoder
{
    public function __construct(private string $key)
    {
    }

    /**
     * Encodes a JSON Web Token (JWT) into a string representation by creating a header, payload, and signature using HMAC-SHA256 algorithm.
     *
     * @param JSONWebToken $token The JSON Web Token object to be encoded.
     * @return string The encoded JSON Web Token as a string.
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
