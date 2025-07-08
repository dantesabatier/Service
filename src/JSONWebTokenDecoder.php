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

    /**
     * Decodes the given string data into a JSONWebToken object.
     *
     * @param string $data The encoded data to be decoded.
     * @return JSONWebToken The decoded JSON Web Token object.
     */
    public function decode(string $data): JSONWebToken
    {
        return $this->strategy->decode($data);
    }
}
