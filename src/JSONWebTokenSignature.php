<?php

namespace Sabatier\Service;

use JsonSerializable;
use Stringable;

/**
 * Represents a JSON Web Token (JWT) signature.
 */
readonly class JSONWebTokenSignature implements Stringable, JsonSerializable
{
    public function __construct(public string $value = "")
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
