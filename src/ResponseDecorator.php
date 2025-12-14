<?php

namespace Sabatier\Service;

abstract class ResponseDecorator
{
    public function __construct(public Response $response)
    {
    }
}
