<?php

namespace Sabatier\Service;

use Sabatier\Foundation\HTTPStatusCode;

/**
 * Class ForbiddenException
 * @package Sabatier\Service
 */
class ForbiddenException extends InvalidRequestException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message, HTTPStatusCode::forbidden);
    }
}
