<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Sanitizes response headers for status codes that must not include a body.
 *
 * This transformer removes content-related headers when the HTTP status code
 * explicitly forbids a message body, ensuring compliance with HTTP semantics.
 *
 * The following headers are removed when applicable:
 * - Content-Type
 * - Content-Length
 * - Content-Disposition
 *
 * This behavior applies only to status codes where a response body is not allowed
 * (e.g., 204 No Content, 304 Not Modified). Status codes that may include a body,
 * such as 201 Created, are intentionally excluded.
 *
 * This transformer does not modify the response body or status code.
 */
final class ResponseHeaderSanitizerTransformer extends ResponseTransformer
{
    public function __construct(Response $response)
    {
        $headers = $response->allHeaderFields;
        if (match ($response->statusCode) {
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
