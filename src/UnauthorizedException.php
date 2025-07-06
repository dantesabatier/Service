<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Represents an exception thrown when an unauthorized request is encountered.
 * This exception typically indicates that the client is not authenticated or does not have the necessary permissions.
 */
class UnauthorizedException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::unauthorized);
    }
}
