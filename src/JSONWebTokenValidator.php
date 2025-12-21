<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Date;
use function Sabatier\Foundation\localized_string;

/**
 * Validates the values of a JSON Web Token.
 */
readonly class JSONWebTokenValidator
{
    private float $now;

    public function __construct(private string $issuer, private string $algorithm)
    {
        $this->now = new Date()->timeIntervalSinceReferenceDate;
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
        if ($payload->nbf && $payload->nbf > $this->now) {
            throw new JSONWebTokenException(localized_string("Access token is not yet valid."), JWTTokenNotYetValidErrorCode);
        }
        if ($payload->exp && $payload->exp < $this->now) {
            throw new JSONWebTokenException(localized_string("Access token has expired."), JWTTokenExpiredErrorCode);
        }
        if ($payload->iss && $payload->iss !== $this->issuer) {
            throw new JSONWebTokenException(localized_string("Access token issuer is invalid."), JWTTokenInvalidErrorCode);
        }
    }
}
