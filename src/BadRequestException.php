<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Represents an exception triggered by a bad request.
 *
 * This exception typically indicates that the server cannot or will not process the request due to a client-side error (such as malformed request syntax).
 */
final class BadRequestException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::badRequest);
    }
}
