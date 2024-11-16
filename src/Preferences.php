<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\UserDefaults;

#[Endpoint]
class Preferences extends Responder
{
    public Response $response {
        get {
            $request = $this->request;
            switch ($request->httpMethod) {
                case HTTPRequestMethod::get:
                case HTTPRequestMethod::post:
                case HTTPRequestMethod::put:
                case HTTPRequestMethod::patch:
                case HTTPRequestMethod::delete:
                    if ($request->httpMethod !== HTTPRequestMethod::get) {
                        $body = $request->getParsedBody();
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
                    break;
                case HTTPRequestMethod::options:
                    break;
                default:
                    throw new MethodNotAllowedException();
            }
            return new Response($this);
        }
    }
}
