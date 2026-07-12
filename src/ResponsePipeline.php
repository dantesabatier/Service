<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/** @internal */
final readonly class ResponsePipeline
{
    /** @param Set<class-string<ResponseTransformer>> $transformers */
    public function __construct(private Set $transformers, private ResponseTransformerContext $context = new ResponseTransformerContext())
    {
    }

    public function process(Response $response): Response
    {
        foreach ($this->transformers as $transformer) {
            $response = new $transformer($response, $this->context)->response;
        }
        return $response;
    }
}
