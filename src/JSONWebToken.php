<?php

namespace Sabatier\Service;

use InvalidArgumentException;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\human_readable_value;

/**
 * Class JSONWebToken
 * @package Sabatier\Service
 */
class JSONWebToken extends ObjectClass
{
    public readonly ?string $tokenString;
    public readonly mixed $payload;
    public readonly ?string $issuer;
    public readonly bool $isValid;
    public readonly URL $url;

    /**
     * @param string $key
     * @param array<string, mixed>|null $payload
     * @param string|null $token
     * @param string|null $issuer
     */
    public function __construct(public readonly string $key, ?array $payload = null, ?string $token = null, ?string $issuer = null)
    {
        unset($this->tokenString);
        unset($this->payload);
        unset($this->issuer);
        unset($this->isValid);
        unset($this->url);
        if ($payload) {
            $this->payload = $payload;
        } elseif ($token) {
            $this->tokenString = $token;
        } else {
            throw new InvalidArgumentException();
        }
        if ($issuer) {
            $this->issuer = $issuer;
        }
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            'tokenString' => jwt_generate($this->payload, $this->key),
            'isValid' => jwt_validate((string)$this->tokenString, $this->key, $this->issuer),
            'payload' => jwt_payload((string)$this->tokenString, $this->key, $this->issuer, $this->isValid),
            'url' => new URL(request_url()),
            'issuer' => $this->url->host,
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function description(): string
    {
        return human_readable_value($this->tokenString);
    }

    public function jsonSerialize(): ?string
    {
        return $this->tokenString;
    }
}
