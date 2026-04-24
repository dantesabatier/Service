<?php

namespace Sabatier\Service;

use JsonException;

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
