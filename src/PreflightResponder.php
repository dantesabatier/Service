<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
class PreflightResponder extends Responder
{
    public Response $response {
        get => new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($this->request->url, HTTPStatusCode::noContent))->response, $this->request, $this->corsPolicy)->response;
    }

    public function handleResponseIfNeeded(): void
    {
        if (!$this->request->isPreflight) {
            return;
        }
        $this->response->send();
    }
}
