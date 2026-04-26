<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JsonSerializable;
use Override;

/**
 * Represents the header section of a JSON Web Token (JWT).
 * @psalm-type JSONWebTokenHeaderRawValue array{alg: string, typ: string}
 */
final class JSONWebTokenHeader implements JsonSerializable
{
    /** @var JSONWebTokenHeaderRawValue $rawValue */
    private(set) array $rawValue;
    public string $alg {
        get => $this->rawValue[__PROPERTY__];
    }
    public string $typ {
        get => $this->rawValue[__PROPERTY__];
    }

    /**
     * Constructor for initializing the JSON Web Token header.
     *
     * @param string $alg The algorithm used for the token.
     * @param string $typ The type of token.
     */
    public function __construct(string $alg = JSONWebTokenSigningAlgorithm::hs256->value, string $typ = JWTTypeValue)
    {
        $this->rawValue = [JWTAlgorithmKey => $alg, JWTTypeKey => $typ];
    }

    /**
     * Creates a new instance of JSONWebTokenHeader with the provided raw value.
     *
     * @param JSONWebTokenHeaderRawValue $rawValue The raw header value.
     * @return JSONWebTokenHeader
     */
    public static function header(array $rawValue = [JWTAlgorithmKey => JSONWebTokenSigningAlgorithm::hs256->value, JWTTypeKey => JWTTypeValue]): JSONWebTokenHeader
    {
        $header = new JSONWebTokenHeader();
        $header->rawValue = $rawValue;
        return $header;
    }

    /**
     * @return JSONWebTokenHeaderRawValue
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->rawValue;
    }
}
