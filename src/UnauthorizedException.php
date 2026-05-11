<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Thrown when a request cannot be fulfilled because the client is not authenticated.
 *
 * Maps to HTTP 401 Unauthorized. Throw this when valid credentials are absent or invalid —
 * for example, when no `Authorization` header is present, the token is expired, or the
 * signature cannot be verified.
 *
 * Prefer `ForbiddenException` (403) when the client IS authenticated but lacks the required
 * permission for the requested resource.
 *
 * @see ForbiddenException
 */
class UnauthorizedException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::unauthorized);
    }
}
