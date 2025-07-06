<?php

namespace Sabatier\Service;

/**
 * Represents a JSON Web Token (JWT) and provides properties for common JWT claims.
 */
readonly class JSONWebToken
{
    public function __construct(public JSONWebTokenHeader $header, public JSONWebTokenPayload $payload, public JSONWebTokenSignature $signature = new JSONWebTokenSignature())
    {
    }
}
