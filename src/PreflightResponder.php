<?php

namespace Sabatier\Service;

/** @internal */
class PreflightResponder extends Responder
{
    public Response $response {
        get => new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($this->request->url))->response, $this->request, $this->corsPolicy)->response;
    }

    public function respondToPreflightIfNeeded(): void
    {
        if (!$this->request->isPreflight) {
            return;
        }
        $this->response->send();
    }
}
