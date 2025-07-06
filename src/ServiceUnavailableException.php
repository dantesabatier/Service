<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when a service is unavailable.
 *
 * This exception should be used to signal that the requested service is temporarily unavailable and cannot process the request.
 */
class ServiceUnavailableException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::serviceUnavailable);
    }
}
