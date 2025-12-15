<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
class PreflightResponder extends Responder
{
    public Response $response {
        get => new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($this->request->url, HTTPStatusCode::noContent))->response, $this->request)->response;
    }

    public function handle(): void
    {
        if ($this->request->httpMethod !== HTTPRequestMethod::options) {
            return;
        }
        $this->response->send();
    }
}
