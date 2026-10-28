<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Decorates a Response object by adding headers that prevent caching.
 *
 * This transformer modifies the response's header fields to include a
 * "Cache-Control" directive with a no-store policy and sets "Pragma"
 * and "Expires" for broader client compatibility.
 */
final class NoCacheHeaderTransformer extends ResponseTransformer
{
    /**
     * @param Response $response The response to transform.
     * @param ResponseTransformerContext $context The request and the active policies the transformation reads.
     */
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $headers = $response->allHeaderFields;
        $headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0";
        $headers["Pragma"] = "no-cache";
        $headers["Expires"] = "0";
        parent::__construct($response, $context);
    }
}
