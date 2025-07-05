<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Date;

/**
 * @phpstan-import-type JSONWebTokenValues from JSONWebToken
 * @internal
 */
class JSONWebTokenHS256DecoderStrategy extends JSONWebTokenDecoderStrategy
{
    public static JSONWebTokenSigningAlgorithm $algorithm = JSONWebTokenSigningAlgorithm::hs256;

    #[Override]
    public function decode(string $data): JSONWebToken
    {
        $components = explode(".", $data);
        if (count($components) !== 3) {
            throw new JSONWebTokenException("Access token is missing.");
        }
        [$header, $payload, $signature] = $components;
        $unsigned = sprintf("%s.%s", $header, $payload);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        if ($signature !== $signed) {
            throw new JSONWebTokenException("Access token is not valid.");
        }
        $date = new Date();
        /** @var JSONWebTokenValues $values */
        $values = json_decode(base64_decode($payload), true);
        $token = JSONWebToken::token($values);
        if ($token->nbf && $token->nbf > $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token is not yet valid.");
        }
        if ($token->exp && $token->exp < $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token has expired.");
        }
        if ($token->iss && $token->iss !== $this->issuer) {
            throw new JSONWebTokenException("Access token issuer is invalid.");
        }
        return $token;
    }
}
