<?php

namespace Sabatier\Service;

use Exception;
use function Sabatier\Foundation\base64_url_encode;

/**
 * Class representing a strategy for encoding a JSON Web Token (JWT).
 */
abstract class JSONWebTokenEncoderStrategy extends JSONWebTokenCoderStrategy
{
    /**
     * Encodes a JSON Web Token (JWT) into a string representation.
     *
     * @param JSONWebToken $token The JSON Web Token object to be encoded.
     * @return string The encoded JSON Web Token as a string.
     * @throws Exception
     */
    public function encode(JSONWebToken $token): string
    {
        $header = base64_url_encode(json_encode($token->header, JSON_THROW_ON_ERROR));
        $payload = base64_url_encode(json_encode($token->payload, JSON_THROW_ON_ERROR));
        $signature = $this->sign("$header.$payload");
        return "$header.$payload.$signature";
    }

    /**
     * Signs the provided input string and returns a signed string.
     *
     * @param string $unsigned The input string to be signed.
     * @return string The signed string.
     */
    abstract protected function sign(string $unsigned): string;
}
