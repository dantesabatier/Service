<?php

namespace Sabatier\Service;

/**
 * Abstract class responsible for defining the strategy to decode and validate JSON Web Tokens (JWT).
 */
abstract class JSONWebTokenDecoderStrategy
{
    public function __construct(public string $key, public ?string $issuer = null)
    {
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
