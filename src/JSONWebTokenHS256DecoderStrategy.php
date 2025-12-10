<?php

namespace Sabatier\Service;

use Override;
use function Sabatier\Foundation\base64_url_encode;
use function Sabatier\Foundation\localized_string;

/** @internal */
class JSONWebTokenHS256DecoderStrategy extends JSONWebTokenDecoderStrategy
{
    protected JSONWebTokenSigningAlgorithm $algorithm {
        get => JSONWebTokenSigningAlgorithm::hs256;
    }

    #[Override]
    protected function verify(string $unsigned, string $signature, string $header, string $payload): void
    {
        assert(is_string($this->key));
        $signed = base64_url_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed) {
            throw new JSONWebTokenException(localized_string("Access token is not valid."));
        }
    }

    #[Override]
    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::hs256;
    }
}
