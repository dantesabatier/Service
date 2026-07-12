<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\string_is_equal;

/**
 * Represents an HTTP Authorization header containing authentication parameters and scheme information.
 */
final class AuthorizationHeader
{
    /** @var string $name The name of the authentication parameter */
    private(set) string $name {
        set {
            $value = $value |> trim(...);
            $this->name = match (true) {
                string_is_equal($value, AuthenticationScheme::aws->value, CompareOptions::caseInsensitive) => AuthenticationScheme::aws->value,
                default => $value
                        |> strtolower(...)
                        |> ucfirst(...)
            };
        }
    }
    /** @var string $value The value of the authentication parameter */
    private(set) string $value {
        set {
            $this->value = trim($value);
        }
    }
    /** @var AuthenticationScheme $scheme The associated authentication scheme */
    private(set) AuthenticationScheme $scheme {
        get => $this->scheme ??= AuthenticationScheme::tryFrom($this->name) ?? AuthenticationScheme::basic;
    }

    /**
     * @param string $rawValue The raw value of the authentication parameter
     */
    public function __construct(private(set) string $rawValue {
        set {
            $components = preg_split("/\\s+/", $value, 2);
            if (count($components) !== 2) {
                $components = [AuthenticationScheme::basic->value, ""];
            }
            [$k, $v] = $components;
            $this->name = $k;
            $this->value = $v;
            $this->rawValue = $value;
        }
    })
    {
    }
}
