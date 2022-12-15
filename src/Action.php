<?php

namespace Sabatier\Service;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Action
{
    public function __construct(public string $path, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] public string $method = HTTPRequestMethod::post)
    {
    }
}
