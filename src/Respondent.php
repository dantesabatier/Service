<?php

namespace Sabatier\Service;

abstract class Respondent
{
    public abstract Response $response {
        get;
    }

    public function __construct(public Responder $responder)
    {
    }
}
