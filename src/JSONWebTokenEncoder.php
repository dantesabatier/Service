<?php

namespace Sabatier\Service;

use Exception;
use InvalidArgumentException;

class JSONWebTokenEncoder
{
    public function __construct(private readonly string $key, private string $algorithm = "HS256" {
        set {
            hash_algos()[$value] ?: throw new InvalidArgumentException();
            $this->algorithm = $value;
        }
    })
    {
    }

    /**
     * @throws Exception
     */
    public function encode(JSONWebToken $token): string
    {
        $header = base64_encode(json_encode(["alg" => $this->algorithm, "typ" => "JWT"], JSON_THROW_ON_ERROR));
        $encoded = base64_encode(json_encode($token, JSON_THROW_ON_ERROR));
        $unsigned = sprintf("%s.%s", $header, $encoded);
        $signed = base64_encode(hash_hmac("sha256", $unsigned, $this->key, true));
        return sprintf("%s.%s", $unsigned, $signed);
    }
}
