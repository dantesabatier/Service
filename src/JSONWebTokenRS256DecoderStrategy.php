<?php

namespace Sabatier\Service;

use Override;

/**
 * @phpstan-import-type JSONWebTokenValues from JSONWebToken
 * @internal
 */
class JSONWebTokenRS256DecoderStrategy extends JSONWebTokenDecoderStrategy
{
    public static function canInit(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }

    #[Override]
    public function decode(string $data): JSONWebToken
    {
        $components = explode(".", $data);
        if (count($components) !== 3) {
            throw new JSONWebTokenException("Access token is missing.");
        }
        [$header, $payload, $signature] = $components;
        $unsigned = "$header.$payload";
        $signature = base64_decode($signature);
        $pkey = openssl_pkey_get_public($this->key);
        if (!openssl_verify($unsigned, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new JSONWebTokenException(openssl_error_string() ?: "Access token is not valid.");
        }
        /** @var JSONWebTokenValues $values */
        $values = json_decode(base64_decode($payload), true);
        $token = JSONWebToken::token($values);
        $validator = new JSONWebTokenValidator($this->issuer);
        $validator->validate($token);
        return $token;
    }
}
