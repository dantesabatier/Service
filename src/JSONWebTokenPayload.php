<?php

namespace Sabatier\Service;

use JsonSerializable;
use Sabatier\Foundation\Date;

/**
 * @psalm-type JSONWebTokenPayloadRawValue array{iss: string|null, sub: string|null, aud: string|null, exp: float|null, nbf: float|null, iat: float|null, jti: string|null, scp: string[]|null, authz: string[]|null}
 */
class JSONWebTokenPayload implements JsonSerializable
{
    /** @var JSONWebTokenPayloadRawValue */
    private(set) array $rawValue;

    /** @var string|null issuer */
    public ?string $iss {
        get => $this->rawValue[JWTIssuerKey] ?? null;
    }
    /** @var string|null subject */
    public ?string $sub {
        get => $this->rawValue[JWTSubjectKey] ?? null;
    }
    /** @var string|null audience */
    public ?string $aud {
        get => $this->rawValue[JWTAudienceKey] ?? null;
    }
    /** @var float|null expiration */
    public ?float $exp {
        get => $this->rawValue[JWTExpirationTimeKey] ?? null;
    }
    /** @var float|null not before */
    public ?float $nbf {
        get => $this->rawValue[JWTNotBeforeTimeKey] ?? null;
    }
    /** @var float|null issued at */
    public ?float $iat {
        get => $this->rawValue[JWTIssuedAtTimeKey] ?? null;
    }
    /** @var string|null JWT ID */
    public ?string $jti {
        get => $this->rawValue[JWTIdKey] ?? null;
    }
    /** @var string[]|null technical scopes */
    public ?array $scp {
        get => $this->rawValue[JWTScopesKey] ?? null;
    }
    /** @var string[]|null authorization scopes */
    public ?array $authz {
        get => $this->rawValue[JWTAuthorizationScopesKey] ?? null;
    }

    /**
     * Constructor
     *
     * @param string|null $iss Issuer
     * @param string|null $sub Subject
     * @param string|null $aud Audience
     * @param Date|null $exp Expiration
     * @param Date|null $nbf Not before
     * @param Date|null $iat Issued at
     * @param string|null $jti JWT ID
     * @param string[]|null $scp Technical scopes
     * @param string[]|null $authz Authorization scopes
     */
    public function __construct(?string $iss = null, ?string $sub = null, ?string $aud = null, ?Date $exp = null, ?Date $nbf = null, ?Date $iat = null, ?string $jti = null, ?array $scp = null, ?array $authz = null)
    {
        $this->rawValue = [JWTIssuerKey => $iss, JWTSubjectKey => $sub, JWTAudienceKey => $aud, JWTExpirationTimeKey => $exp?->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $nbf?->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $iat?->timeIntervalSinceReferenceDate, JWTIdKey => $jti, JWTScopesKey => $scp, JWTAuthorizationScopesKey => $authz];
    }

    /**
     * Factory from raw value
     *
     * @param JSONWebTokenPayloadRawValue $rawValue
     * @return JSONWebTokenPayload
     */
    public static function payload(array $rawValue): JSONWebTokenPayload
    {
        $payload = new self();
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
