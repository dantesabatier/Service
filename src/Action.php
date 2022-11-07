<?php

namespace Sabatier\Service;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\HTTPRequestMethod;

#[Attribute(Attribute::TARGET_METHOD)]
class Action
{
    public function __construct(public readonly string $path, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] public readonly string $method = HTTPRequestMethod::post)
    {
    }
}
