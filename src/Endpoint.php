<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Attribute;

/**
 * An attribute to designate a class as an endpoint for an HTTP request.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Endpoint
{
    /**
     * @param string|null $path Optional path for the endpoint. Defaults to the name of the class.
     * @param array<class-string<ResponseTransformer>> $transformers Transformers applied to every action of this endpoint.
     */
    public function __construct(public ?string $path = null, public array $transformers = [])
    {
    }
}
