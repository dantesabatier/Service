<?php

namespace Sabatier\Service;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * An attribute to mark a method as an endpoint for an HTTP request.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Action
{
    public function __construct(#[ExpectedValues([HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete])] public string $method = HTTPRequestMethod::post, public ?string $path = null)
    {
    }
}
