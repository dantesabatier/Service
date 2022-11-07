<?php

namespace Sabatier\Service;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Endpoint
{
    public function __construct(public readonly string $path)
    {
    }
}
