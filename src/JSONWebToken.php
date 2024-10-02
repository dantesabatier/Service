<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;

/**
 * @psalm-type JSONWebTokenValues = array{iss: string|null, sub: string|null, aud: string|null, exp: float, nbf: float, iat: float, jti: string|null, dat: mixed}
 */
class JSONWebToken implements JsonSerializable
{
    /** @var JSONWebTokenValues */
    public array $allValues;

    public function __construct(public ?string $iss = null, public ?string $sub = null, public ?string $aud = null, public float $exp = 0, public float $nbf = PHP_FLOAT_MAX, public float $iat = 0, public ?string $jti = null, public mixed $dat = null)
    {
        $this->allValues = ["iss" => $this->iss, "sub" => $this->sub, "aud" => $this->aud, "exp" => $this->exp, "nbf" => $this->nbf, "iat" => $this->iat, "jti" => $this->jti, "dat" => $this->dat];
    }

    /**
     * @return JSONWebTokenValues
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->allValues;
    }
}
