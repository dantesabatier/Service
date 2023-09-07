<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\UndefinedKeyException;

readonly class Authorization
{
    /** @var Dictionary<string> */
    public Dictionary $parameters;

    public function __construct(public string $authScheme, public string $credentials = "")
    {
    }

    public function __get(string $name)
    {
        $this->$name = match ($name) {
            "parameters" => (function (): Dictionary {
                preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->credentials, $matches);
                return new Dictionary(array_combine($matches[1], $matches[2]));
            })(),
            default => throw new UndefinedKeyException()
        };
    }
}