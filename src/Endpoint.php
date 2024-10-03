<?php

namespace Sabatier\Service;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Endpoint
{
    public function __construct(public ?string $path = null)
    {
    }
}
