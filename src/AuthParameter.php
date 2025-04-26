<?php

namespace Sabatier\Service;

/**
 * Represents a parameter used in authentication
 */
readonly class AuthParameter
{
    /** @var string $name The name of the authentication parameter */
    public string $name;
    /** @var string $value The value of the authentication parameter */
    public string $value;
    /** @var AuthenticationScheme $scheme The associated authentication scheme */
    public AuthenticationScheme $scheme;

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
        $this->scheme = AuthenticationScheme::tryFrom(ucfirst($name)) ?? AuthenticationScheme::basic;
    }
}
