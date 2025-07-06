<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when an action is forbidden or not allowed.
 *
 * This exception typically represents an HTTP 403 Forbidden error,
 * indicating that the server understands the request but refuses to authorize it.
 */
class ForbiddenException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::forbidden);
    }
}
