<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

class BadRequestException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::badRequest);
    }
}
