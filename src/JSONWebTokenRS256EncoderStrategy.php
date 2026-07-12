<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use function Sabatier\Foundation\base64_url_encode;

/** @internal */
final class JSONWebTokenRS256EncoderStrategy extends JSONWebTokenEncoderStrategy
{
    #[Override]
    protected function sign(string $unsigned): string
    {
        openssl_sign($unsigned, $signature, $this->key, OPENSSL_ALGO_SHA256);
        return base64_url_encode($signature);
    }

    #[Override]
    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }
}
