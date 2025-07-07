<?php

namespace Sabatier\Service;

use JsonSerializable;

/**
 * Represents the header section of a JSON Web Token (JWT).
 * @phpstan-type JSONWebTokenHeaderRawValue array{alg: string, typ: string}
 */
class JSONWebTokenHeader implements JsonSerializable
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
    public function __construct(string $alg = "HS256", string $typ = "JWT")
    {
        $this->rawValue = ["alg" => $alg, "typ" => $typ];
    }

    /**
     * Creates a new instance of JSONWebTokenHeader with the provided raw value.
     *
     * @param JSONWebTokenHeaderRawValue $rawValue The raw header value.
     * @return JSONWebTokenHeader
     */
    public static function header(array $rawValue = ["alg" => "HS256", "typ" => "JWT"]): JSONWebTokenHeader
    {
        $header = new JSONWebTokenHeader();
        $header->rawValue = $rawValue;
        return $header;
    }

    /**
     * @return JSONWebTokenHeaderRawValue
     */
    public function jsonSerialize(): array
    {
        return $this->rawValue;
    }
}
