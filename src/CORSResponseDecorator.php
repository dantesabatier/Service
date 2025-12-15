<?php

namespace Sabatier\Service;

/** @internal */
final class CORSResponseDecorator extends ResponseDecorator
{
    public function __construct(Response $response, Request $request)
    {
        $headers = $response->allHeaderFields;
        if ($origin = $request->valueForHttpHeaderField("Origin")) {
            $headers["Access-Control-Allow-Origin"] = $origin;
            $headers["Access-Control-Allow-Credentials"] = "true";
            $headers["Vary"] = "Origin";
        }
        if ($value = $request->valueForHttpHeaderField("Access-Control-Request-Method")) {
            $headers["Access-Control-Allow-Methods"] = $value;
        }
        if ($value = $request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
            $headers["Access-Control-Allow-Headers"] = $value;
        }
        parent::__construct($response);
    }
}
