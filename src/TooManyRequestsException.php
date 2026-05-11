<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Thrown when a client exceeds the configured rate limit.
 *
 * Maps to HTTP 429 Too Many Requests. `ErrorResponder` catches this exception
 * and adds a `Retry-After` header indicating how many seconds the client must
 * wait before making another request.
 *
 * @see RateLimitPolicy
 * @see ErrorResponder
 */
final class TooManyRequestsException extends InvalidRequestException
{
    /**
     * @param int $retryAfter Seconds the client should wait before retrying.
     */
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("", HTTPStatusCode::tooManyRequests);
    }
}
