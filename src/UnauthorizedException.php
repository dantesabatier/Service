<?php

namespace Sabatier\Service;

use Sabatier\Foundation\HTTPStatusCode;

/**
 * Class UnauthorizedException
 * @package Sabatier\Service
 */
class UnauthorizedException extends InvalidRequestException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message, HTTPStatusCode::unauthorized);
    }
}
