<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;
use function Sabatier\Foundation\string_split_trimmed;

/**
 * Applies CORS response headers derived from the CORSPolicy in the ResponseTransformerContext.
 *
 * Validates the incoming `Origin` header against the policy's allowed origins. When the origin
 * is permitted, writes the appropriate `Access-Control-*` headers. Wildcard origin (`*`) is
 * emitted only when credentials are not required; otherwise the exact matched origin is reflected
 * and a `Vary: Origin` header is added. Requests without an `Origin` header, or from disallowed
 * origins, pass through without modification.
 *
 * @see CORSPolicy
 * @see Application::$corsPolicy
 */
final class CORSResponseTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $request = $context->request;
        $policy = $context->corsPolicy ?? Application::shared()->corsPolicy;
        if ($request === null || !($origin = $request->valueForHttpHeaderField("Origin"))) {
            parent::__construct($response, $context);
            return;
        }
        if (!$policy->allowsOrigin($origin)) {
            parent::__construct($response, $context);
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
            $allowedHeaders = $policy->allowedHeaders->map(fn(string $allowedHeader): string => strtolower($allowedHeader))->intersection(new Set(string_split_trimmed($requestedHeaders))->map(fn(string $requestedHeader): string => strtolower($requestedHeader)));
            if (!$allowedHeaders->isEmpty) {
                $headers["Access-Control-Allow-Headers"] = $allowedHeaders->join(", ");
            }
        }
        parent::__construct($response, $context);
    }
}
