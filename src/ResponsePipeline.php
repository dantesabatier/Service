<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/**
 * Applies an ordered set of response transformers to a response.
 *
 * This is the supported entry point for a responder that overrides `public Response $response`
 * and needs to run the transformer chain by hand. Compose the pipeline from the sets already
 * exposed by `Responder`, honoring the two-leg order — user transformers first, infrastructure
 * transformers second:
 *
 *     public Response $response {
 *         get => new ResponsePipeline($this->transformers->union($this->infrastructureTransformers), $this->transformerContext)
 *             ->process($response);
 *     }
 *
 * Prefer the `$data` pattern where it suffices; reach for this only when overriding `$response`
 * for full control (custom status with no body, streaming, PersistentSpace-level behavior).
 */
final readonly class ResponsePipeline
{
    /** @param Set<class-string<ResponseTransformer>> $transformers */
    public function __construct(private Set $transformers, private ResponseTransformerContext $context = new ResponseTransformerContext())
    {
    }

    /**
     * Runs the response through each transformer in order, threading the output of one as the input of the next.
     *
     * Every transformer is instantiated with the current response and the shared context, and its produced
     * `response` becomes the input for the next. Returns the response unchanged when the set is empty.
     *
     * @param Response $response The response to feed into the first transformer.
     * @return Response The response produced by the last transformer, or the original when the set is empty.
     */
    public function process(Response $response): Response
    {
        foreach ($this->transformers as $transformer) {
            $response = new $transformer($response, $this->context)->response;
        }
        return $response;
    }
}
