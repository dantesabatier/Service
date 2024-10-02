<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Date;

readonly class JSONWebTokenDecoder
{
    public function __construct(private string $key, private ?string $issuer = null)
    {
    }

    /**
     * @throws JSONWebTokenException
     */
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
        $token = new JSONWebToken();
        $decoded = json_decode(base64_decode($payload), true);
        foreach ($decoded as $key => $value) {
            $token->$key = $value;
        }
        $date = new Date();
        if ($token->nbf && $token->nbf > $date->timeIntervalSinceReferenceDate) {
            throw new JSONWebTokenException("Access token has expired.");
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
