<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\string_is_equal;

/**
 * Represents an HTTP request header with its name and value extracted from a raw input string.
 */
class RequestHeader
{
    /** @var string $name The name of the authentication parameter */
    public readonly string $name;
    /** @var string $value The value of the authentication parameter */
    public readonly string $value;
    /** @var Dictionary<string> */
    private(set) Dictionary $parameters {
        get {
            if (!isset($this->parameters)) {
                preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->value, $matches);
                $this->parameters = new Dictionary(array_combine($matches[1], $matches[2]));
            }
            return $this->parameters;
        }
    }

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
        $name = $name |> trim(...);
        $this->name = match (true) {
            string_is_equal($name, AuthenticationScheme::aws->value, CompareOptions::caseInsensitive) => AuthenticationScheme::aws->value,
            string_is_equal($name, AuthenticationScheme::oauth->value, CompareOptions::caseInsensitive) => AuthenticationScheme::oauth->value,
            default => $name
                    |> strtolower(...)
                    |> ucfirst(...)
        };
        $this->value = $value;
    }
}
