<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

final class ResponseHeaderSanitizerDecorator extends ResponseDecorator
{
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
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
