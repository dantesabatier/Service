<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JsonException;

/**
 * A response transformer that serializes the response body to JSON.
 *
 * Sets `Content-Type: application/json` and encodes `$response->body`
 * using `json_encode` with `JSON_THROW_ON_ERROR` and `JSON_PRESERVE_ZERO_FRACTION`.
 *
 * Typically declared in `#[Action]` or `#[Endpoint]` attributes alongside
 * `NoCacheHeaderTransformer` for mutation endpoints that return structured data.
 */
final class JSONTransformer extends ResponseTransformer
{
    /**
     * @throws JsonException
     */
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "application/json";
        $response->body = json_encode($response->body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        parent::__construct($response, $context);
    }
}
