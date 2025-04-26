<?php

namespace Sabatier\Service;

/**
 * A class designed to provide a {@see Responder}'s response in more complex cases.
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
