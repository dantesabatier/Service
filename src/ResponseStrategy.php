<?php

namespace Sabatier\Service;

/**
 * A strategy to provide a {@see Response} for a specific {@see Responder}.
 */
abstract class ResponseStrategy
{
    abstract public Response $response {
        get;
    }

    public function __construct(public readonly Responder $responder)
    {
    }
}
