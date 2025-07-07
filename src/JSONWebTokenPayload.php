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

    /**
     * Constructor to initialize the object with JWT-related properties.
     *
     * @param string|null $iss Issuer of the token.
     * @param string|null $sub Subject of the token.
     * @param string|null $aud Audience for which the token is intended.
     * @param Date|null $exp Expiration time of the token.
     * @param Date|null $nbf Not before time (the token is valid on or after this time).
     * @param Date|null $iat Issued at time (the time at which the token was issued).
     * @param string|null $jti Unique identifier for the token.
     * @param string|null $username Username associated with the token.
     */
    public function __construct(?string $iss = null, ?string $sub = null, ?string $aud = null, ?Date $exp = null, ?Date $nbf = null, ?Date $iat = null, ?string $jti = null, ?string $username = null)
    {
        $this->rawValue = ["iss" => $iss, "sub" => $sub, "aud" => $aud, "exp" => $exp?->timeIntervalSinceReferenceDate, "nbf" => $nbf?->timeIntervalSinceReferenceDate, "iat" => $iat?->timeIntervalSinceReferenceDate, "jti" => $jti, "username" => $username];
    }

    /**
     * Creates a new JSONWebTokenPayload instance with the given raw value.
     *
     * @param JSONWebTokenPayloadRawValue $rawValue The raw value to be assigned to the payload.
     * @return JSONWebTokenPayload The created JSONWebTokenPayload instance.
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
