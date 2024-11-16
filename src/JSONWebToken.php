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
    public ?string $iss {
        get => $this->allValues["iss"] ?? null;
    }
    public ?string $sub {
        get => $this->allValues["sub"] ?? null;
    }
    public ?string $exp {
        get => $this->allValues["exp"] ?? null;
    }
    public ?string $aud {
        get => $this->allValues["aud"] ?? null;
    }
    public ?string $nbf {
        get => $this->allValues["nbf"] ?? null;
    }
    public ?string $iat {
        get => $this->allValues["iat"] ?? null;
    }
    public ?string $jti {
        get => $this->allValues["jti"] ?? null;
    }
    public mixed $sec {
        get => $this->allValues["sec"] ?? null;
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
