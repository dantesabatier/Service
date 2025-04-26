<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;

/**
 * Represents a JSON Web Token (JWT) and provides properties for common JWT claims.
 * @phpstan-type JSONWebTokenValues array{iss: string|null, sub: string|null, aud: string|null, exp: float|null, nbf: float|null, iat: float|null, jti: string|null, username: string|null}
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
    /** @var float|null expiration time */
    public ?float $exp {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null audience */
    public ?string $aud {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var float|null not before */
    public ?float $nbf {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var float|null issued at */
    public ?float $iat {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    /** @var string|null JWT ID */
    public ?string $jti {
        get => $this->allValues[__PROPERTY__] ?? null;
    }
    public ?string $username {
        get => $this->allValues[__PROPERTY__] ?? null;
    }

    public function __construct(?string $iss = null, ?string $sub = null, ?string $aud = null, ?float $exp = null, ?float $nbf = null, ?float $iat = null, ?string $jti = null, ?string $username = null)
    {
        $this->allValues = ["iss" => $iss, "sub" => $sub, "aud" => $aud, "exp" => $exp, "nbf" => $nbf, "iat" => $iat, "jti" => $jti, "username" => $username];
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
