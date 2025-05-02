<?php

namespace Sabatier\Service;

/**
 * A class responsible for decoding JSON Web Tokens (JWTs).
 */
readonly class JSONWebTokenDecoder
{
    public function __construct(private JSONWebTokenDecoderStrategy $strategy)
    {
    }

    public function decode(string $data): JSONWebToken
    {
        return $this->strategy->decode($data);
    }
}
