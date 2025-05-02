<?php

namespace Sabatier\Service;

use Exception;

/**
 * This class provides functionality to encode a JSON Web Token (JWT).
 */
readonly class JSONWebTokenEncoder
{
    public function __construct(private JSONWebTokenEncoderStrategy $strategy)
    {
    }

    /**
     * @throws Exception
     */
    public function encode(JSONWebToken $token): string
    {
        return $this->strategy->encode($token);
    }
}
