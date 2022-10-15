<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;

/** @internal */
class Me extends Endpoint
{
    public function allowedMethods(): ArrayClass
    {
        return new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::get]);
    }

    public function response(): HTTPURLResponse
    {
        $authorization = $this->service->authorization;
        if (!$authorization->token?->isValid || !($user = $authorization->user)) {
            throw new UnauthorizedException();
        }
        $this->content = json_encode($user, JSON_PRESERVE_ZERO_FRACTION);
        return new HTTPURLResponse($this->url, HTTPStatusCode::ok, null, new Dictionary(["Content-Type" => "application/json; charset=utf-8"]));
    }
}
