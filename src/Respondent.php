<?php

namespace Sabatier\Service;

/**
 * A strategy to provide a {@see Responder}'s for a specific HTTP method.
 */
abstract class Respondent
{
    public abstract Response $response {
        get;
    }

    public function __construct(public Responder $responder)
    {
    }
}
