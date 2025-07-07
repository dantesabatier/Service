<?php

namespace Sabatier\Service;

readonly class RequestHeader
{
    /** @var string $name The name of the authentication parameter */
    public string $name;
    /** @var string $value The value of the authentication parameter */
    public string $value;

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
    }
}
