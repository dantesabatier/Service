<?php

namespace Sabatier\Service;

use OpenSSLAsymmetricKey;

/**
 * An abstract class that defines the strategy for encoding and decoding JSON Web Tokens (JWTs).
 */
abstract class JSONWebTokenCoderStrategy
{
    /**
     * Constructs a new instance of the class with the specified key.
     *
     * @param OpenSSLAsymmetricKey|string $key The key to be used, which can be an OpenSSLAsymmetricKey instance or a string representation of a key.
     */
    public function __construct(public readonly OpenSSLAsymmetricKey|string $key)
    {
    }

    /**
     * Determines whether this class can be initialized using the specified JSONWebTokenSigningAlgorithm.
     *
     * @param JSONWebTokenSigningAlgorithm $algorithm An instance of a JWT signing algorithm.
     * @return bool Returns true if this class can be initialized using the specified algorithm; otherwise, false.
     */
    public abstract static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool;
}
