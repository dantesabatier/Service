<?php

namespace Sabatier\Service;

use Override;

/** @internal */
class JSONWebTokenRS256EncoderStrategy extends JSONWebTokenEncoderStrategy
{
    public static function canInit(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }

    #[Override]
    public function encode(JSONWebToken $token): string
    {
        $header = base64_encode(json_encode(["alg" => JSONWebTokenSigningAlgorithm::rs256->value, "typ" => "JWT"], JSON_THROW_ON_ERROR));
        $encoded = base64_encode(json_encode($token, JSON_THROW_ON_ERROR));
        $unsigned = "$header.$encoded";
        if (!openssl_sign($unsigned, $signature, $this->key, OPENSSL_ALGO_SHA256)) {
            throw new JSONWebTokenException("Unable to sign JWT with RS256.");
        }
        $signed = base64_encode($signature);
        return "$unsigned.$signed";
    }
}
