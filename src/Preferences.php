<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\UserDefaults;

/** @internal */
#[Endpoint]
final class Preferences extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::patch]);
    }
    public Response $response {
        get {
            try {
                $defaults = UserDefaults::standard();
                $request = $this->request;
                $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
                $this->session->start();
                if ($request->httpMethod === HTTPRequestMethod::patch) {
                    foreach ($request->parsedBody as $key => $value) {
                        $defaults->setObject($value, $key);
                    }
                }
                return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new Response($request->url, HTTPStatusCode::ok, body: $defaults->dictionaryRepresentation()))->response)->response, $request, $this->corsPolicy)->response;
            } finally {
                $this->session->commit();
            }
        }
    }
}
