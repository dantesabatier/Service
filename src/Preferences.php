<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\UserDefaults;

/** @internal */
#[Endpoint]
final class Preferences extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::patch]);
    }
    #[Override]
    public Response $response {
        get {
            try {
                $request = $this->request;
                $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $defaults = UserDefaults::standard();
                if ($request->httpMethod === HTTPRequestMethod::patch) {
                    foreach ($request->parsedBody as $key => $value) {
                        $defaults->setObject($value, $key);
                    }
                }
                return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new Response($request->url, HTTPStatusCode::ok, body: $defaults->dictionaryRepresentation()))->response)->response, $request, $this->corsPolicy)->response;
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
