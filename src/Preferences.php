<?php

namespace Sabatier\Service;

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
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]);
    }
    public Response $response {
        get {
            $request = $this->request;
            $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
            if ($request->httpMethod !== HTTPRequestMethod::get) {
                $body = $request->parsedBody;
                foreach ($body as $key => $value) {
                    UserDefaults::standard()->setObject($value, $key);
                }
            }
            if ($request->httpMethod === HTTPRequestMethod::delete) {
                $this->statusCode = HTTPStatusCode::noContent;
            } else {
                $this->content = json_encode(UserDefaults::standard()->dictionaryRepresentation(), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                $this->headerFields["Content-Type"] = "application/json";
            }
            return new Response($this);
        }
    }
}
