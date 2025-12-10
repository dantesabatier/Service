<?php

namespace Sabatier\Service;

use OpenSSLAsymmetricKey;
use Override;
use function Sabatier\Foundation\localized_string;

/** @internal */
class JSONWebTokenRS256DecoderStrategy extends JSONWebTokenDecoderStrategy
{
    protected JSONWebTokenSigningAlgorithm $algorithm {
        get => JSONWebTokenSigningAlgorithm::rs256;
    }

    #[Override]
    protected function verify(string $unsigned, string $signature, string $header, string $payload): void
    {
        $signature = base64_decode($signature);
        $pkey = openssl_pkey_get_public($this->key);
        assert($pkey instanceof OpenSSLAsymmetricKey);
        if (!openssl_verify($unsigned, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new JSONWebTokenException(openssl_error_string() ?: localized_string("Access token is not valid."));
        }
    }

    #[Override]
    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }
}
