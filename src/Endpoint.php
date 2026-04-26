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
     * @param string|null $path Opcional, path del endpoint. Por omisión, se usa el nombre de la clase.
     * @param array<class-string<ResponseTransformer>> $transformers Transformers aplicados a todas las acciones de este endpoint.
     */
    public function __construct(public ?string $path = null, public array $transformers = [])
    {
    }
}
