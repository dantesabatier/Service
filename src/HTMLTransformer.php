<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Sets `Content-Type: text/html; charset=utf-8` on the response.
 *
 * Typically declared in `#[Endpoint]` attributes on ViewControllers alongside
 * `CacheHeaderTransformer` when the HTML response should be cacheable.
 */
final class HTMLTransformer extends ResponseTransformer
{
    /**
     * @param Response $response The response to transform.
     * @param ResponseTransformerContext $context The request and the active policies the transformation reads.
     */
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "text/html; charset=utf-8";
        parent::__construct($response, $context);
    }
}
