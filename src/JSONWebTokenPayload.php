<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JsonSerializable;
use Override;
use Sabatier\Foundation\Date;

/**
 * @psalm-type JSONWebTokenPayloadRawValue array{iss: string|null, sub: string|null, aud: string|null, exp: float|null, nbf: float|null, iat: float|null, jti: string|null, ver: int|null, scp: string[]|null, authz: string[]|null, enb: boolean|null}
 */
final class JSONWebTokenPayload implements JsonSerializable
{
    /** @var JSONWebTokenPayloadRawValue */
    private(set) array $rawValue;

    /** @var string|null issuer */
    public ?string $issuer {
        get => $this->rawValue[JWTIssuerKey] ?? null;
    }
    /** @var string|null subject */
    public ?string $subject {
        get => $this->rawValue[JWTSubjectKey] ?? null;
    }
    /** @var bool|null enable */
    public ?bool $isEnabled {
        get => $this->rawValue[JWTEnabledKey] ?? null;
    }
    /** @var string|null audience */
    public ?string $audience {
        get => $this->rawValue[JWTAudienceKey] ?? null;
    }
    /** @var float|null expiration */
    public ?float $expiration {
        get => $this->rawValue[JWTExpirationTimeKey] ?? null;
    }
    /** @var float|null not before */
    public ?float $notBefore {
        get => $this->rawValue[JWTNotBeforeTimeKey] ?? null;
    }
    /** @var float|null issued at */
    public ?float $issuedAt {
        get => $this->rawValue[JWTIssuedAtTimeKey] ?? null;
    }
    /** @var string|null JWT ID */
    public ?string $jwtID {
        get => $this->rawValue[JWTIdKey] ?? null;
    }
    /** @var int|null version */
    public ?int $version {
        get => $this->rawValue[JWTVersionKey] ?? null;
    }
    /** @var string[]|null technical scopes */
    public ?array $technicalScopes {
        get => $this->rawValue[JWTScopesKey] ?? null;
    }
    /** @var string[]|null authorization scopes */
    public ?array $authorizationScopes {
        get => $this->rawValue[JWTAuthorizationScopesKey] ?? null;
    }

    /**
     * Constructor
     *
     * @param string|null $issuer Issuer
     * @param string|null $subject Subject
     * @param bool|null $isEnabled Enable
     * @param string|null $audience Audience
     * @param Date|null $expirationDate Expiration
     * @param Date|null $notBefore Not before
     * @param Date|null $issuedAt Issued at
     * @param string|null $jwtID JWT ID
     * @param int|null $version Version
     * @param string[]|null $technicalScopes Technical scopes
     * @param string[]|null $authorizationScopes Authorization scopes
     */
    public function __construct(?string $issuer = null, ?bool $isEnabled = null, ?string $subject = null, ?string $audience = null, ?Date $expirationDate = null, ?Date $notBefore = null, ?Date $issuedAt = null, ?string $jwtID = null, ?int $version = null, ?array $technicalScopes = null, ?array $authorizationScopes = null)
    {
        $this->rawValue = [JWTIssuerKey => $issuer, JWTSubjectKey => $subject, JWTEnabledKey => $isEnabled, JWTAudienceKey => $audience, JWTExpirationTimeKey => $expirationDate?->timeIntervalSinceReferenceDate, JWTNotBeforeTimeKey => $notBefore?->timeIntervalSinceReferenceDate, JWTIssuedAtTimeKey => $issuedAt?->timeIntervalSinceReferenceDate, JWTIdKey => $jwtID, JWTVersionKey => $version, JWTScopesKey => $technicalScopes, JWTAuthorizationScopesKey => $authorizationScopes];
    }

    /**
     * Factory from raw value.
     *
     * Coerces the type-sensitive claims to their declared types so that a token carrying, for example, a string `exp` or `ver` cannot bypass the loose comparisons performed by the access evaluators. Absent claims are left absent so that the evaluators continue to treat a missing claim as an unset condition.
     *
     * The input is the untrusted, freshly decoded token payload, so it is typed as a raw associative array rather than the narrowed claim shape; this method is what produces a value conforming to that shape.
     *
     * @param array<string, mixed> $rawValue
     * @return JSONWebTokenPayload
     */
    public static function payload(array $rawValue): JSONWebTokenPayload
    {
        foreach ([JWTExpirationTimeKey, JWTNotBeforeTimeKey, JWTIssuedAtTimeKey] as $key) {
            if (isset($rawValue[$key]) && is_scalar($rawValue[$key])) {
                $rawValue[$key] = (float)$rawValue[$key];
            }
        }
        if (isset($rawValue[JWTVersionKey]) && is_scalar($rawValue[JWTVersionKey])) {
            $rawValue[JWTVersionKey] = (int)$rawValue[JWTVersionKey];
        }
        if (isset($rawValue[JWTEnabledKey])) {
            $rawValue[JWTEnabledKey] = (bool)$rawValue[JWTEnabledKey];
        }
        $payload = new self();
        /** @var JSONWebTokenPayloadRawValue $rawValue */
        $payload->rawValue = $rawValue;
        return $payload;
    }

    /**
     * @return JSONWebTokenPayloadRawValue
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->rawValue;
    }
}
