<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;

/**
 * This class provides functionality to encode a JSON Web Token (JWT).
 */
final readonly class JSONWebTokenEncoder
{
    public function __construct(private JSONWebTokenEncoderStrategy $strategy)
    {
    }

    /**
     * Encodes the given JSON Web Token (JWT) using the defined strategy.
     *
     * @param JSONWebToken $token The JSON Web Token to be encoded.
     * @return string The encoded representation of the JSON Web Token.
     * @throws Exception
     */
    public function encode(JSONWebToken $token): string
    {
        return $this->strategy->encode($token);
    }
}
