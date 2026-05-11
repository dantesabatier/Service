<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when a requested operation or functionality is not implemented.
 *
 * This exception is used to indicate that the server or application has recognized the request but lacks the ability to fulfill it due to unimplemented functionality.
 */
final class UnimplementedException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::unimplemented);
    }
}
