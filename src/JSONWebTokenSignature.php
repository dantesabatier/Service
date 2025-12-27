<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;
use Stringable;

/**
 * Represents a JSON Web Token (JWT) signature.
 */
final readonly class JSONWebTokenSignature implements Stringable, JsonSerializable
{
    public function __construct(public string $value = "")
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
