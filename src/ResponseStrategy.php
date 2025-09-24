<?php

namespace Sabatier\Service;

/**
 * A strategy to provide a {@see Response} for a specific HTTP method.
 */
abstract class ResponseStrategy
{
    abstract public Response $response {
        get;
    }

    public function __construct(public Responder $responder)
    {
    }
}
