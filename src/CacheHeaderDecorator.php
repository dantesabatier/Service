<?php

namespace Sabatier\Service;

final class CacheHeaderDecorator extends ResponseDecorator
{
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        $headers["Cache-Control"] = "public, max-age=3600";
        parent::__construct($response);
    }
}
