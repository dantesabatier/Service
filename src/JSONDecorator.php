<?php

namespace Sabatier\Service;

use JsonException;

/**
 * Decorates a Response object by encoding its body to a JSON format
 * and setting the appropriate Content-Type header.
 *
 * This class ensures the response body is properly converted to JSON
 * and any encoding issues are handled by throwing an exception.
 *
 * @throws JsonException If the JSON encoding fails.
 */
final class JSONDecorator extends ResponseDecorator
{
    /**
     * @throws JsonException
     */
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "application/json";
        $response->body = json_encode($response->body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        parent::__construct($response);
    }
}
