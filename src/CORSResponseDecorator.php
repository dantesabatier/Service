<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\string_split_trimmed;

/** @internal */
final class CORSResponseDecorator extends ResponseDecorator
{
    public function __construct(Response $response, Request $request, CORSPolicy $policy)
    {
        if (!($origin = $request->valueForHttpHeaderField("Origin"))) {
            parent::__construct($response);
            return;
        }
        if (!$policy->allowsOrigin($origin)) {
            parent::__construct($response);
            return;
        }
        $headers = $response->allHeaderFields;
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
            $headers["Access-Control-Allow-Methods"] = $policy->allowedMethods->map(fn(string $allowedMethod): string => strtoupper($allowedMethod))->join(", ");
        }
        $requestedHeaders = $request->valueForHttpHeaderField("Access-Control-Request-Headers");
        if ($requestedHeaders) {
            $allowedHeaders = $policy->allowedHeaders->union(new Set(string_split_trimmed($requestedHeaders))->filter(fn(string $requestedHeader): bool => !$policy->allowedHeaders->contains(fn(string $allowedHeader): bool => string_is_equal($allowedHeader, $requestedHeader, CompareOptions::caseInsensitive))));
            if (!$allowedHeaders->isEmpty) {
                $headers["Access-Control-Allow-Headers"] = $allowedHeaders->join(", ");
            }
        }
        parent::__construct($response);
    }
}
