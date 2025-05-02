<?php

namespace Sabatier\Service;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Attribute used to mark an instance method of a {@see Responder} subclass as an endpoint for an HTTP request.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Action
{
    public function __construct(#[ExpectedValues([HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::patch, HTTPRequestMethod::delete])] public string $method = HTTPRequestMethod::post, public ?string $path = null)
    {
    }
}
