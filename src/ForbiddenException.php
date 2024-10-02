<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

class ForbiddenException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::forbidden);
    }
}
