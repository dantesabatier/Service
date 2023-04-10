<?php

namespace Sabatier\Service;

use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/** @internal */
readonly class HeaderField
{
    public string $name;
    public string $value;

    public function __construct(public string $rawValue)
    {
        $index = (int)strpos($this->rawValue, " ");
        $this->name = trim(substring_to_index($this->rawValue, $index));
        $this->value = trim(substring_from_index($this->rawValue, $index));
    }
}
