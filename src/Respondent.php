<?php

namespace Sabatier\Service;

abstract class Respondent
{
    abstract public Response $response {
        get;
    }

    public function __construct(public Responder $responder)
    {
    }
}
