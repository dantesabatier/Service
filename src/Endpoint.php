<?php

namespace Sabatier\Service;

use Attribute;

/**
 * An attribute to designate a class as an endpoint with an optional path parameter.
 *
 * The specified path can be used to define a particular route or location; if not provided, the class name will serve as the endpoint path.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Endpoint
{
    /**
     * Constructor method for initializing the class with an optional path.
     *
     * @param string|null $path The file path or null if no path is provided. If not provided, the class name will be used as the path.
     */
    public function __construct(public ?string $path = null)
    {
    }
}
