<?php

namespace Sabatier\Service;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Endpoint
{
    public function __construct(public string $path)
    {
    }
}
