<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;

/** @internal */
class Authenticate extends Endpoint
{
    public function allowedMethods(): ArrayClass
    {
        return new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }

    public function response(): HTTPURLResponse
    {
        if (!($username = $this->service->authorization->credential?->user)) {
            throw new UnauthorizedException();
        }
        $date = new Date();
        $token = new JSONWebToken($this->service->tokenKey, ['iat' => $date->timeIntervalSinceReferenceDate, 'jti' => base64_encode(random_bytes(16)), 'iss' => $this->url->host, 'nbf' => $date->timeIntervalSinceReferenceDate, 'exp' => $date->addingTimeInterval(60 * 60 * $this->service->tokenValidity)->timeIntervalSinceReferenceDate, 'username' => $username]);
        $this->content = json_encode($token);
        return new HTTPURLResponse($this->url, HTTPStatusCode::ok, null, new Dictionary(["Content-Type" => "application/json; charset=utf-8"]));
    }
}
