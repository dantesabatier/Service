<?php

namespace Sabatier\Service;

use Exception;
use OpenSSLAsymmetricKey;
use Sabatier\Foundation\UserDefaults;

/**
 * Service for encoding JSON Web Tokens (JWTs).
 * @phpstan-import-type JSONWebTokenHeaderRawValue from JSONWebTokenHeader
 * @phpstan-import-type JSONWebTokenPayloadRawValue from JSONWebTokenPayload
 */
class JSONWebTokenService
{
    /** @var JSONWebTokenSigningAlgorithm The signing algorithm for JSON Web Tokens (JWT), defaults to the HS256 algorithm. */
    private JSONWebTokenSigningAlgorithm $algorithm {
        get => $this->algorithm ??= JSONWebTokenSigningAlgorithm::tryFrom((string)UserDefaults::standard()->string(JWTSignatureAlgorithmPreferenceKey)) ?? JSONWebTokenSigningAlgorithm::hs256;
    }
    /** @var JSONWebTokenEncoderStrategy The encoder strategy instance based on the algorithm and key. */
    private JSONWebTokenEncoderStrategy $encoderStrategy {
        get {
            if (!isset($this->encoderStrategy)) {
                $strategyClass = JSONWebTokenCoderStrategyFactory::shared()->getStrategyClass(JSONWebTokenCoderStrategyFactory::shared()->encoderStrategies, $this->algorithm);
                $this->encoderStrategy = new $strategyClass($this->key);
            }
            return $this->encoderStrategy;
        }
    }
    /** @var JSONWebTokenDecoderStrategy The decoder strategy instance based on the algorithm and key. */
    private JSONWebTokenDecoderStrategy $decoderStrategy {
        get {
            if (!isset($this->decoderStrategy)) {
                $strategyClass = JSONWebTokenCoderStrategyFactory::shared()->getStrategyClass(JSONWebTokenCoderStrategyFactory::shared()->decoderStrategies, $this->algorithm);
                $this->decoderStrategy = new $strategyClass($this->key, $this->issuer);
            }
            return $this->decoderStrategy;
        }
    }

    public function __construct(private readonly OpenSSLAsymmetricKey|string $key, private readonly ?string $issuer = null)
    {
    }

    /**
     * Encodes the provided payload into a JWT token.
     *
     * @param JSONWebTokenPayloadRawValue $payloadRawValue The raw payload data to encode into the token.
     * @return string The encoded JWT token as a string.
     * @throws Exception
     */
    public function encode(array $payloadRawValue): string
    {
        /** @var JSONWebTokenHeaderRawValue $headerRawValue */
        $headerRawValue = ["alg" => $this->algorithm->value, "typ" => "JWT"];
        $header = JSONWebTokenHeader::header($headerRawValue);
        $payload = JSONWebTokenPayload::payload($payloadRawValue);
        $token = new JSONWebToken($header, $payload);
        return new JSONWebTokenEncoder($this->encoderStrategy)->encode($token);
    }

    /**
     * Decodes a given JSON web token string into a JSONWebToken object.
     *
     * @param string $token The encoded JSON web token string that needs to be decoded.
     * @return JSONWebToken The decoded JSONWebToken object.
     * @throws Exception
     */
    public function decode(string $token): JSONWebToken
    {
        return new JSONWebTokenDecoder($this->decoderStrategy)->decode($token);
    }
}
