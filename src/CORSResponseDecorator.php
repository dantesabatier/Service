<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

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
        if (match ($response->statusCode) {
            HTTPStatusCode::created,
            HTTPStatusCode::noContent,
            HTTPStatusCode::resetContent,
            HTTPStatusCode::notModified => true,
            default => false
        }) {
            $headers->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        parent::__construct($response);
    }
}
