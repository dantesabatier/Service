<?php

namespace Sabatier\Service;

use Override;
use function Sabatier\Foundation\base64_url_encode;

/** @internal */
final class JSONWebTokenHS256EncoderStrategy extends JSONWebTokenEncoderStrategy
{
    #[Override]
    protected function sign(string $unsigned): string
    {
        assert(is_string($this->key));
        return base64_url_encode(hash_hmac("sha256", $unsigned, $this->key, true));
    }

    #[Override]
    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::hs256;
    }
}
