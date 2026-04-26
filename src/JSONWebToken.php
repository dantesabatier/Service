<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Represents a JSON Web Token (JWT) and provides properties for common JWT claims.
 */
final readonly class JSONWebToken
{
    public function __construct(public JSONWebTokenHeader $header, public JSONWebTokenPayload $payload, public JSONWebTokenSignature $signature = new JSONWebTokenSignature())
    {
    }
}
