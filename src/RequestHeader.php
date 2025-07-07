<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;

/**
 * Represents an HTTP request header with its name and value extracted from a raw input string.
 */
readonly class RequestHeader
{
    /** @var string $name The name of the authentication parameter */
    public string $name;
    /** @var string $value The value of the authentication parameter */
    public string $value;
    /** @var Dictionary<covariant string> */
    public Dictionary $parameters;

    /**
     * @param string $rawValue The raw value of the authentication parameter
     */
    public function __construct(public string $rawValue)
    {
        $components = explode(" ", $this->rawValue, 2);
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$name, $value] = $components;
        $this->name = $name;
        $this->value = $value;
        preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->value, $matches);
        $this->parameters = new Dictionary(array_combine($matches[1], $matches[2]));
    }
}
