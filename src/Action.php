<?php

namespace Sabatier\Service;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Represents an attribute that can be used to annotate methods with an HTTP request method and an optional path.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Action
{
    public function __construct(#[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] public string $method = HTTPRequestMethod::post, public ?string $path = null)
    {
    }
}
