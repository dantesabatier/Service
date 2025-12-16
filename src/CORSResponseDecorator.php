<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/** @internal */
final class CORSResponseDecorator extends ResponseDecorator
{
    public function __construct(Response $response, Request $request, CORSPolicy $policy)
    {
        $headers = $response->allHeaderFields;
        $origin = $request->valueForHttpHeaderField("Origin");
        if (!$origin) {
            parent::__construct($response);
            return;
        }
        if (!$policy->allowsOrigin($origin)) {
            parent::__construct($response);
            return;
        }
        if ($policy->allowsOrigin("*") && !$policy->allowCredentials) {
            $headers["Access-Control-Allow-Origin"] = "*";
        } else {
            $headers["Access-Control-Allow-Origin"] = $origin;
            $headers["Vary"] = "Origin";
        }
        if ($policy->allowCredentials) {
            $headers["Access-Control-Allow-Credentials"] = "true";
        }
        if (!$policy->allowedMethods->isEmpty) {
            $headers["Access-Control-Allow-Methods"] = $policy->allowedMethods->map(fn(string $e): string => strtoupper($e))->join(", ");
        }
        $requestedHeaders = $request->valueForHttpHeaderField("Access-Control-Request-Headers");
        if ($requestedHeaders && !$policy->allowedHeaders->isEmpty) {
            $allowedHeaders = clone $policy->allowedHeaders->map(fn(string $e): string => strtolower($e));
            $allowedHeaders->formIntersection(new Set(explode(",", $requestedHeaders))->map(fn(string $e): string => strtolower($e)));
            $headers["Access-Control-Allow-Headers"] = $allowedHeaders->join(", ");
        }
        parent::__construct($response);
    }
}
