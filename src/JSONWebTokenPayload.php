<?php

namespace Sabatier\Service;

use JsonSerializable;
use Sabatier\Foundation\Date;

/**
 * Represents the payload of a JSON Web Token (JWT).
 * @phpstan-type JSONWebTokenPayloadRawValue array{iss: string|null, sub: string|null, aud: string|null, exp: float|null, nbf: float|null, iat: float|null, jti: string|null, username: string|null}
 */
class JSONWebTokenPayload implements JsonSerializable
{
    /** @var JSONWebTokenPayloadRawValue */
    private(set) array $rawValue;
    /** @var string|null issuer */
    public ?string $iss {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    /** @var string|null subject */
    public ?string $sub {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    /** @var float|null expiration time */
    public ?float $exp {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    /** @var string|null audience */
    public ?string $aud {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    /** @var float|null not before */
    public ?float $nbf {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    /** @var float|null issued at */
    public ?float $iat {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    /** @var string|null JWT ID */
    public ?string $jti {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }
    public ?string $username {
        get => $this->rawValue[__PROPERTY__] ?? null;
    }

    public function __construct(?string $iss = null, ?string $sub = null, ?string $aud = null, ?Date $exp = null, ?Date $nbf = null, ?Date $iat = null, ?string $jti = null, ?string $username = null)
    {
        $this->rawValue = ["iss" => $iss, "sub" => $sub, "aud" => $aud, "exp" => $exp?->timeIntervalSinceReferenceDate, "nbf" => $nbf?->timeIntervalSinceReferenceDate, "iat" => $iat?->timeIntervalSinceReferenceDate, "jti" => $jti, "username" => $username];
    }

    /**
     * @param JSONWebTokenPayloadRawValue $rawValue
     * @return JSONWebTokenPayload
     */
    public static function payload(array $rawValue): JSONWebTokenPayload
    {
        $payload = new JSONWebTokenPayload();
        $payload->rawValue = $rawValue;
        return $payload;
    }

    /**
     * @return JSONWebTokenPayloadRawValue
     */
    public function jsonSerialize(): array
    {
        return $this->rawValue;
    }
}
