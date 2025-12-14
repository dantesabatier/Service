<?php

namespace Sabatier\Service;

class OptionsPersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        get => new Response($this->request->url);
    }
}
