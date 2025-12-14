<?php

namespace Sabatier\Service;

use JsonException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\UserDefaults;

/** @internal */
#[Endpoint]
class Preferences extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::get]);
    }
    public Response $response {
        /**
         * @throws JsonException
         */
        get {
            $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
            return new CORSResponseDecorator(new JSONDecorator(new Response($this->request->url, HTTPStatusCode::ok, body: UserDefaults::standard()->dictionaryRepresentation()))->response, $this->request)->response;
        }
    }
}
