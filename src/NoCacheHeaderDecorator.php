<?php

namespace Sabatier\Service;

/**
 * Decorates a Response object by adding headers that prevent caching.
 *
 * This decorator modifies the response's header fields to include a
 * "Cache-Control" directive with a no-store policy, and sets "Pragma"
 * and "Expires" for broader client compatibility.
 */
final class NoCacheHeaderDecorator extends ResponseDecorator
{
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0";
        $headers["Pragma"] = "no-cache";
        $headers["Expires"] = "0";
        parent::__construct($response);
    }
}
