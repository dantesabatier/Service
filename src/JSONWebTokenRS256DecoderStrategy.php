<?php

namespace Sabatier\Service;

use Override;

/** @internal */
class JSONWebTokenRS256DecoderStrategy extends JSONWebTokenDecoderStrategy
{
    public JSONWebTokenSigningAlgorithm $algorithm {
        get => JSONWebTokenSigningAlgorithm::rs256;
    }

    #[Override]
    protected function verify(string $unsigned, string $signature, string $header, string $payload): void
    {
        $signature = base64_decode($signature);
        $pkey = openssl_pkey_get_public($this->key);
        if (!openssl_verify($unsigned, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new JSONWebTokenException(openssl_error_string() ?: "Access token is not valid.");
        }
    }

    #[Override]
    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }
}
