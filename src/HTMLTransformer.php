<?php

namespace Sabatier\Service;

/**
 * A response transformer that ensures the response is treated as HTML.
 * Adds "Content-Type: text/html; charset=utf-8" and default cache headers.
 *
 * Can be applied to any Response object, typically used in ViewControllers.
 */
final class HTMLTransformer extends ResponseTransformer
{
    /**
     * Initialize the transformer with the given Response.
     *
     * @param Response $response The response to decorate.
     */
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "text/html; charset=utf-8";
        parent::__construct($response);
    }
}
