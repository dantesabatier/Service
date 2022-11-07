<?php

namespace Sabatier\Service;

use Sabatier\Foundation\HTTPStatusCode;

class ConflictException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::conflict);
    }
}
