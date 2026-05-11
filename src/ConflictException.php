<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown to indicate a conflict occurs in the request processing.
 */
final class ConflictException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::conflict);
    }
}
