<?php

namespace Sabatier\Service;

use JsonSerializable;
use Override;
use Sabatier\Foundation\UndefinedKeyException;

/**
 * @psalm-type JSONWebTokenValues = array{iss: string|null, sub: string|null, aud: string|null, exp: float|null, nbf: float|null, iat: float|null, jti: string|null, sec: mixed}
 *
 * @property-read string|null $iss
 * @property-read string|null $sub
 * @property-read string|null $aud
 * @property-read float|null $exp
 * @property-read float|null $nbf
 * @property-read float|null $iat
 * @property-read string|null $jti
 * @property-read mixed $sec
 * @property-read JSONWebTokenValues $allValues
 */
class JSONWebToken implements JsonSerializable
{
    /** @var JSONWebTokenValues */
    private array $allValues;

    public function __construct(?string $iss = null, ?string $sub = null, ?string $aud = null, ?float $exp = null, ?float $nbf = null, ?float $iat = null, ?string $jti = null, mixed $sec = null)
    {
        $this->allValues = ["iss" => $iss, "sub" => $sub, "aud" => $aud, "exp" => $exp, "nbf" => $nbf, "iat" => $iat, "jti" => $jti, "sec" => $sec];
    }

    public function __get(string $name)
    {
        return match ($name) {
            "iss", "sub", "aud", "exp", "nbf", "iat", "jti", "sec" => $this->allValues[$name] ?? null,
            "allValues" => $this->allValues,
            default => throw new UndefinedKeyException(),
        };
    }

    /**
     * @param JSONWebTokenValues $values
     * @return JSONWebToken
     */
    public static function token(array $values): JSONWebToken
    {
        $token = new JSONWebToken();
        foreach ($values as $key => $value) {
            $token->allValues[$key] = $value;
        }
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
