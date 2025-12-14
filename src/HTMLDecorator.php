<?php

namespace Sabatier\Service;

/**
 * A response decorator that ensures the response is treated as HTML.
 * Adds "Content-Type: text/html; charset=utf-8" and default cache headers.
 *
 * Can be applied to any Response object, typically used in ViewControllers.
 */
final class HTMLDecorator extends ResponseDecorator
{
    /**
     * Initialize the decorator with the given Response.
     *
     * @param Response $response The response to decorate.
     */
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "text/html; charset=utf-8";
        $headers["Cache-Control"] = $headers["Cache-Control"] ?? "no-cache, no-store, must-revalidate, max-age=0";
        parent::__construct($response);
    }
}
