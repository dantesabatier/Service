<?php

namespace Sabatier\Service;

use OpenSSLAsymmetricKey;

/**
 * Class responsible for defining the strategy to decode and validate JSON Web Tokens (JWT).
 */
abstract class JSONWebTokenDecoderStrategy extends JSONWebTokenCoderStrategy
{
    public function __construct(OpenSSLAsymmetricKey|string $key, public readonly ?string $issuer = null)
    {
        parent::__construct($key);
    }

    /**
     * Decodes a JSON Web Token (JWT) string and validates its authenticity and claims based on the header, payload, and signature components.
     *
     * @param string $data The JWT string that needs to be decoded and verified.
     * @return JSONWebToken Returns a validated JSONWebToken object.
     * @throws JSONWebTokenException If the JWT is missing, invalid, expired, not yet valid, or has an invalid issuer.
     */
    public abstract function decode(string $data): JSONWebToken;
}
