<?php

namespace Sabatier\Service;

use OpenSSLAsymmetricKey;

/**
 * Class responsible for defining the strategy to decode and validate JSON Web Tokens (JWT).
 * @psalm-import-type JSONWebTokenHeaderRawValue from JSONWebTokenHeader
 * @psalm-import-type JSONWebTokenPayloadRawValue from JSONWebTokenPayload
 */
abstract class JSONWebTokenDecoderStrategy extends JSONWebTokenCoderStrategy
{
    abstract protected JSONWebTokenSigningAlgorithm $algorithm {
        get;
    }

    public function __construct(OpenSSLAsymmetricKey|string $key, public readonly string $issuer = "")
    {
        parent::__construct($key);
    }

    /**
     * Decodes a JSON Web Token (JWT) string and validates its authenticity and claims based on the header, payload, and signature components.
     *
     * @param string $data The JWT string that needs to be decoded and verified.
     * @return JSONWebToken Returns a validated JSONWebToken object.
     * @throws JSONWebTokenException If the JWT is missing, invalid, expired, not yet valid, or has an invalid issuer.
     */
    public function decode(string $data): JSONWebToken
    {
        $components = explode(JWTComponentDelimiter, $data);
        if (count($components) !== JWTComponentCount) {
            throw new JSONWebTokenException("Access token is missing.");
        }
        [$header, $payload, $signature] = $components;
        $unsigned = "$header.$payload";
        $this->verify($unsigned, $signature, $header, $payload);
        /** @var JSONWebTokenHeaderRawValue $headerRawValue */
        $headerRawValue = json_decode(base64_decode($header), true);
        /** @var JSONWebTokenPayloadRawValue $payloadRawValue */
        $payloadRawValue = json_decode(base64_decode($payload), true);
        $token = new JSONWebToken(JSONWebTokenHeader::header($headerRawValue), JSONWebTokenPayload::payload($payloadRawValue), new JSONWebTokenSignature($signature));
        $validator = new JSONWebTokenValidator($this->issuer, $this->algorithm->value);
        $validator->validate($token);
        return $token;

    }

    /**
     * Verifies the provided signature against the unsigned data, header, and payload.
     *
     * @param string $unsigned The data that was signed.
     * @param string $signature The signature to be verified.
     * @param string $header The header information associated with the signature.
     * @param string $payload The payload information associated with the signature.
     * @throws JSONWebTokenException
     */
    abstract protected function verify(string $unsigned, string $signature, string $header, string $payload): void;
}
