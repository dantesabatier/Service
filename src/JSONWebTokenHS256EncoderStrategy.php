<?php

namespace Sabatier\Service;

use Override;

/** @internal */
class JSONWebTokenHS256EncoderStrategy extends JSONWebTokenEncoderStrategy
{
    public static JSONWebTokenSigningAlgorithm $algorithm = JSONWebTokenSigningAlgorithm::hs256;

    #[Override]
    public function encode(JSONWebToken $token): string
    {
        $header = base64_encode(json_encode(["alg" => self::$algorithm->value, "typ" => "JWT"], JSON_THROW_ON_ERROR));
        $encoded = base64_encode(json_encode($token, JSON_THROW_ON_ERROR));
        $unsigned = "$header.$encoded";
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return "$unsigned.$signed";
    }
}
