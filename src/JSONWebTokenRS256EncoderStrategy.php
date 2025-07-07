<?php

namespace Sabatier\Service;

use Override;
use function Sabatier\Foundation\base64_url_encode;

/** @internal */
class JSONWebTokenRS256EncoderStrategy extends JSONWebTokenEncoderStrategy
{
    #[Override]
    public function encode(JSONWebToken $token): string
    {
        $header = base64_url_encode(json_encode($token->header, JSON_THROW_ON_ERROR));
        $encoded = base64_url_encode(json_encode($token->payload, JSON_THROW_ON_ERROR));
        $unsigned = "$header.$encoded";
        if (!openssl_sign($unsigned, $signature, $this->key, OPENSSL_ALGO_SHA256)) {
            throw new JSONWebTokenException(openssl_error_string() ?: "Unable to sign JWT with RS256.");
        }
        $signed = base64_encode($signature);
        return "$unsigned.$signed";
    }

    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }
}
