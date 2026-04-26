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
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "text/html; charset=utf-8";
        parent::__construct($response, $context);
    }
}
