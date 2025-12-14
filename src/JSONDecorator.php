<?php

namespace Sabatier\Service;

use JsonException;

final class JSONDecorator extends ResponseDecorator
{
    /**
     * @throws JsonException
     */
    public function __construct(Response $response)
    {
        $response->body = json_encode($response->body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $headers = $response->allHeaderFields;
        $headers["Content-Type"] = "application/json";
        parent::__construct($response);
    }
}
