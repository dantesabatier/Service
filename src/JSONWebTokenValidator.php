<?php

namespace Sabatier\Service;

use function Sabatier\Foundation\localized_string;

/**
 * Validates the values of a JSON Web Token.
 */
final readonly class JSONWebTokenValidator
{
    public function __construct(private string $issuer, private string $algorithm)
    {
    }

    /**
     * Validates the provided JSON Web Token (JWT) based on its claims.
     *
     * @param JSONWebToken $token The JSON Web Token to be validated.
     * @throws JSONWebTokenException Throws an exception if the token is invalid.
     */
    public function validate(JSONWebToken $token): void
    {
        $header = $token->header;
        if ($header->alg !== $this->algorithm) {
            throw new JSONWebTokenException(localized_string("Access token algorithm is invalid."), JWTTokenInvalidErrorCode);
        }
        $payload = $token->payload;
        if ($payload->issuer && $payload->issuer !== $this->issuer) {
            throw new JSONWebTokenException(localized_string("Access token issuer is invalid."), JWTTokenInvalidErrorCode);
        }
    }
}
