<?php

namespace Sabatier\Service;

use OpenSSLAsymmetricKey;

/**
 * An abstract class that defines the strategy for encoding and decoding JSON Web Tokens (JWTs).
 */
abstract class JSONWebTokenCoderStrategy
{
    public abstract static function canInit(JSONWebTokenSigningAlgorithm $algorithm): bool;

    public function __construct(public readonly OpenSSLAsymmetricKey|string $key)
    {
    }
}
