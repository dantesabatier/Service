<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Date;

/**
 * Validates the values of a JSON Web Token.
 */
readonly class JSONWebTokenValidator
{
    public function __construct(private string $issuer)
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
        $date = new Date();
        if ($token->nbf && $token->nbf > $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token is not yet valid.");
        }
        if ($token->exp && $token->exp < $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token has expired.");
        }
        if ($token->iss && $token->iss !== $this->issuer) {
            throw new JSONWebTokenException("Access token issuer is invalid.");
        }
    }
}
