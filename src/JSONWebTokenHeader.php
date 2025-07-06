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

    public function __construct(string $alg = JSONWebTokenSigningAlgorithm::hs256->value, string $typ = "JWT")
    {
        $this->rawValue = ["alg" => $alg, "typ" => $typ];
    }

    public static function header(array $rawValue): JSONWebTokenHeader
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
