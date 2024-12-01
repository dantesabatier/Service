<?php

namespace Sabatier\Service;

/** @internal */
class PersistentSpaceRespondentDefault extends PersistentSpaceRespondent
{
    public Response $response {
        get => new Response($this->responder);
    }
}
