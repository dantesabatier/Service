<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when a requested resource could not be found.
 *
 * Use this exception to signal when a resource, such as a file, database entry, or API endpoint, is missing or unavailable.
 */
class NotFoundException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::notFound);
    }
}
