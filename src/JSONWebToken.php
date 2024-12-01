<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;

/**
 * @psalm-type JSONWebTokenValues = array{iss: string|null, sub: string|null, aud: string|null, exp: float|null, nbf: float|null, iat: float|null, jti: string|null, sec: mixed}
 */
class JSONWebToken implements JsonSerializable
{
    /** @var JSONWebTokenValues */
    private(set) array $allValues;
    /** @var string|null issuer */
    public ?string $iss {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null subject */
    public ?string $sub {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null expiration time */
    public ?string $exp {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null audience */
    public ?string $aud {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null not before */
    public ?string $nbf {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null issued at */
    public ?string $iat {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null JWT ID */
    public ?string $jti {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    public mixed $sec {
        get => $this->allValues[__PROPERTY__] ?? null;
    }

    public function __construct(?string $iss = null, ?string $sub = null, ?string $aud = null, ?float $exp = null, ?float $nbf = null, ?float $iat = null, ?string $jti = null, mixed $sec = null)
    {
        $this->allValues = ["iss" => $iss, "sub" => $sub, "aud" => $aud, "exp" => $exp, "nbf" => $nbf, "iat" => $iat, "jti" => $jti, "sec" => $sec];
    }

    /**
     * @param JSONWebTokenValues $values
     * @return JSONWebToken
     */
    public static function token(array $values): JSONWebToken
    {
        $token = new JSONWebToken();
        $token->allValues = $values;
        return $token;
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
