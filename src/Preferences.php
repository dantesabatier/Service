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
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::patch]);
    }
    public Response $response {
        /**
         * @throws JsonException
         */
        get {
            $defaults = UserDefaults::standard();
            $request = $this->request;
            $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
            if ($request->httpMethod === HTTPRequestMethod::patch) {
                foreach ($request->parsedBody as $key => $value) {
                    $defaults->setObject($value, $key);
                }
            }
            return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new Response($request->url, HTTPStatusCode::ok, body: $defaults->dictionaryRepresentation()))->response)->response, $request, $this->corsPolicy)->response;
        }
    }
}
