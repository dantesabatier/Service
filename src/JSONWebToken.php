<?php

namespace Sabatier\Service;

use Exception;
use InvalidArgumentException;
use Sabatier\Foundation\ObjectClass;

class JSONWebToken extends ObjectClass
{
    public readonly string $tokenString;
    public readonly ?object $payload;
    public readonly bool $isValid;

    /**
     * @param string $key
     * @param array|object|null $payload
     * @param string|null $token
     * @param string|null $issuer
     */
    public function __construct(public readonly string $key, array|object|null $payload = null, ?string $token = null, public readonly ?string $issuer = null)
    {
        unset($this->tokenString);
        unset($this->payload);
        unset($this->isValid);
        if ($payload) {
            $this->payload = (object)$payload;
        } elseif ($token) {
            $this->tokenString = $token;
        } else {
            throw new InvalidArgumentException();
        }
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            'tokenString' => jwt_generate((object)$this->payload, $this->key),
            'isValid' => jwt_validate($this->tokenString, $this->key, $this->issuer),
            'payload' => jwt_payload($this->tokenString, $this->key, $this->issuer, $this->isValid),
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function description(): string
    {
        return $this->tokenString;
    }

    public function jsonSerialize(): string
    {
        return $this->tokenString;
    }
}
