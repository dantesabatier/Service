<?php

namespace Sabatier\Service;

use Exception;

/**
 * Abstract class representing a strategy for encoding a JSON Web Token (JWT).
 */
abstract class JSONWebTokenEncoderStrategy
{
    public function __construct(public string $key)
    {
    }

    /**
     * Encodes a JSON Web Token (JWT) into a string representation by creating a header, payload, and signature using HMAC-SHA256 algorithm.
     *
     * @param JSONWebToken $token The JSON Web Token object to be encoded.
     * @return string The encoded JSON Web Token as a string.
     * @throws Exception
     */
    public abstract function encode(JSONWebToken $token): string;
}
