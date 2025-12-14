<?php

namespace Sabatier\Service;

/**
 * Decorates a Response object by adding a Cache-Control header to enable
 * caching behavior for the response.
 *
 * This decorator modifies the response's header fields to include a
 * "Cache-Control" directive with the value "public, max-age=3600".
 */
final class CacheHeaderDecorator extends ResponseDecorator
{
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Cache-Control"] = "public, max-age=3600";
        parent::__construct($response);
    }
}
