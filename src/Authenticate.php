<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ProcessInfo;

use function Sabatier\Foundation\fatal_error;

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
        $token = new JSONWebToken(ProcessInfo::processInfo()->environment['SERVICE_TOKEN_KEY'] ?? fatal_error("environment variable \"SERVICE_TOKEN_KEY\" cannot be null"), ['iat' => $date->timeIntervalSinceReferenceDate, 'jti' => base64_encode(random_bytes(16)), 'iss' => $this->url->host, 'nbf' => $date->timeIntervalSinceReferenceDate, 'exp' => $date->addingTimeInterval(60 * 60 * (new Number(ProcessInfo::processInfo()->environment['SERVICE_TOKEN_VALIDITY'] ?? 8))->intValue)->timeIntervalSinceReferenceDate, 'username' => $username]);
        $this->content = json_encode($token);
        return new HTTPURLResponse($this->url, HTTPStatusCode::ok, null, new Dictionary(["Content-Type" => "application/json; charset=utf-8"]));
    }
}
