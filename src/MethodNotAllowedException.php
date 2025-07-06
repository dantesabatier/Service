<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when a requested HTTP method is not allowed for the target resource.
 *
 * This exception indicates that the client has used an HTTP method disallowed by the server for the resource being accessed. It corresponds to the HTTP 405 Method Not Allowed status code.
 *
 * The exception can include an optional message to provide additional context or details about the error.
 */
class MethodNotAllowedException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::methodNotAllowed);
    }
}
