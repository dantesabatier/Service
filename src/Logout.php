<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;

/** @internal */
class Logout extends Endpoint
{
    public function allowedMethods(): ArrayClass
    {
        return new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }

    public function isSecure(): bool
    {
        return false;
    }

    public function response(): HTTPURLResponse
    {
        $this->content = json_encode(true, JSON_THROW_ON_ERROR);
        return new HTTPURLResponse($this->url, HTTPStatusCode::ok, null, new Dictionary(["Content-Type" => "application/json; charset=utf-8"]));
    }
}
