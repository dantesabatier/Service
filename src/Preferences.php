<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\UserDefaults;

#[Endpoint]
class Preferences extends Responder
{
    public function response(): HTTPURLResponse
    {
        $request = $this->request;
        $statusCode = HTTPStatusCode::ok;
        switch ($request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::post:
            case HTTPRequestMethod::put:
            case HTTPRequestMethod::patch:
            case HTTPRequestMethod::delete:
                if ($request->httpMethod !== HTTPRequestMethod::get) {
                    $body = $this->request->getParsedBody();
                    foreach ($body as $key => $value) {
                        UserDefaults::standard()->setObject($value, $key);
                    }
                }
                if ($request->httpMethod === HTTPRequestMethod::delete) {
                    $statusCode = HTTPStatusCode::noContent;
                } else {
                    $this->content = json_encode(UserDefaults::standard()->dictionaryRepresentation(), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                    $this->contentType = "application/json; charset=utf-8";
                }
                break;
            case HTTPRequestMethod::options:
                break;
            default:
                throw new MethodNotAllowedException();
        }
        return new HTTPURLResponse($this->request->url, $statusCode);
    }
}
