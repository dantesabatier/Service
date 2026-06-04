<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Sets `Content-Type: text/plain; charset=utf-8` on the response.
 *
 * Typically declared in `#[Endpoint]` or `#[Action]` attributes when the
 * response body is plain text rather than JSON or HTML.
 */
final class TextTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "text/plain; charset=utf-8";
        parent::__construct($response, $context);
    }
}
