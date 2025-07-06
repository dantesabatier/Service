<?php

namespace Sabatier\Service;

use Override;

/**
 * @internal
 * @phpstan-import-type JSONWebTokenHeaderRawValue from JSONWebTokenHeader
 * @phpstan-import-type JSONWebTokenPayloadRawValue from JSONWebTokenPayload
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
        /** @var JSONWebTokenHeaderRawValue $headerRawValue */
        $headerRawValue = json_decode(base64_decode($header), true);
        /** @var JSONWebTokenPayloadRawValue $payloadRawValue */
        $payloadRawValue = json_decode(base64_decode($payload), true);
        $token = new JSONWebToken(JSONWebTokenHeader::header($headerRawValue), JSONWebTokenPayload::payload($payloadRawValue), new JSONWebTokenSignature($signature));
        $validator = new JSONWebTokenValidator($this->issuer, JSONWebTokenSigningAlgorithm::hs256->value);
        $validator->validate($token);
        return $token;
    }

    public static function isSupported(JSONWebTokenSigningAlgorithm $algorithm): bool
    {
        return $algorithm === JSONWebTokenSigningAlgorithm::hs256;
    }
}
