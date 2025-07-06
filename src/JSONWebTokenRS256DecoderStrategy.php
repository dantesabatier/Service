<?php

namespace Sabatier\Service;

use Override;

/**
 * @internal
 * @phpstan-import-type JSONWebTokenHeaderRawValue from JSONWebTokenHeader
 * @phpstan-import-type JSONWebTokenPayloadRawValue from JSONWebTokenPayload
 */
class JSONWebTokenRS256DecoderStrategy extends JSONWebTokenDecoderStrategy
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
        $signature = base64_decode($signature);
        $pkey = openssl_pkey_get_public($this->key);
        if (!openssl_verify($unsigned, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new JSONWebTokenException(openssl_error_string() ?: "Access token is not valid.");
        }
        /** @var JSONWebTokenHeaderRawValue $headerRawValue */
        $headerRawValue = json_decode(base64_decode($header), true);
        /** @var JSONWebTokenPayloadRawValue $payloadRawValue */
        $payloadRawValue = json_decode(base64_decode($payload), true);
        $token = new JSONWebToken(JSONWebTokenHeader::header($headerRawValue), JSONWebTokenPayload::payload($payloadRawValue), new JSONWebTokenSignature($signature));
        $validator = new JSONWebTokenValidator($this->issuer, JSONWebTokenSigningAlgorithm::rs256->value);
        $validator->validate($token);
        return $token;
    }

    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::rs256;
    }
}
