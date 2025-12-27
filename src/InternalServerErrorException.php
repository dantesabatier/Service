<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when an internal server error occurs.
 * This exception is typically used to indicate a server-side issue
 * that prevents the completion of the request.
 */
final class InternalServerErrorException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::internalServerError);
    }
}
