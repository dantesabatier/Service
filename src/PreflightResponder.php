<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
class PreflightResponder extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options]);
    }
    public Response $response {
        get => new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new Response($this->request->url, HTTPStatusCode::noContent))->response, $this->request, $this->corsPolicy)->response;
    }

    public function respondToPreflightIfNeeded(): void
    {
        if (!$this->request->isPreflight) {
            return;
        }
        $this->response->send();
    }
}
