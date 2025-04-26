<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Date;

/**
 * A readonly class responsible for decoding and verifying JSON Web Tokens (JWTs).
 * This class ensures the authenticity and validity of a given JWT string by checking its header, payload, and signature. Optionally, it can validate the issuer claim if specified during instantiation.
 * @phpstan-import-type JSONWebTokenValues from JSONWebToken
 */
readonly class JSONWebTokenDecoder
{
    public function __construct(private string $key, private ?string $issuer = null)
    {
    }

    /**
     * Decodes a JSON Web Token (JWT) string and validates its authenticity and claims based on the header, payload, and signature components.
     *
     * @param string $data The JWT string that needs to be decoded and verified.
     * @return JSONWebToken Returns a validated JSONWebToken object.
     * @throws JSONWebTokenException If the JWT is missing, invalid, expired, not yet valid, or has an invalid issuer.
     */
    public function decode(string $data): JSONWebToken
    {
        $components = explode(".", $data);
        if (count($components) !== 3) {
            throw new JSONWebTokenException("Access token is missing.");
        }
        [$header, $payload, $signature] = $components;
        $unsigned = sprintf("%s.%s", $header, $payload);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed) {
            throw new JSONWebTokenException("Access token is not valid.");
        }
        $date = new Date();
        /** @var JSONWebTokenValues $values */
        $values = json_decode(base64_decode($payload), true);
        $token = JSONWebToken::token($values);
        if ($token->nbf && $token->nbf > $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token is not yet valid.");
        }
        if ($token->exp && $token->exp < $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token has expired.");
        }
        if ($token->iss && $token->iss !== $this->issuer) {
            throw new JSONWebTokenException("Access token issuer is invalid.");
        }
        return $token;
    }
}
