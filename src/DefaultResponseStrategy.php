<?php

namespace Sabatier\Service;

/** @internal */
class DefaultResponseStrategy extends ResponseStrategy
{
    public Response $response {
        get => new Response($this->responder);
    }
}
