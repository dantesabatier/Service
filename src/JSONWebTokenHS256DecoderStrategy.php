<?php

namespace Sabatier\Service;

use Override;

/**
 * @phpstan-import-type JSONWebTokenValues from JSONWebToken
 * @internal
 */
class JSONWebTokenHS256DecoderStrategy extends JSONWebTokenDecoderStrategy
{
    #[Override]
    public function decode(string $data): JSONWebToken
    {
        $components = explode(".", $data);
        if (count($components) !== 3) {
            throw new JSONWebTokenException("Access token is missing.");
        }
        [$header, $payload, $signature] = $components;
        $unsigned = "$header.$payload";
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed) {
            throw new JSONWebTokenException("Access token is not valid.");
        }
        /** @var JSONWebTokenValues $values */
        $values = json_decode(base64_decode($payload), true);
        $token = JSONWebToken::token($values);
        $validator = new JSONWebTokenValidator($this->issuer);
        $validator->validate($token);
        return $token;
    }

    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::hs256;
    }
}
