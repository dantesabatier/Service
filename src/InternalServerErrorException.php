<?php

namespace Sabatier\Service;

use Sabatier\Foundation\HTTPStatusCode;

/**
 * Class InternalServerErrorException
 * @package Sabatier\Service
 */
class InternalServerErrorException extends InvalidRequestException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message, HTTPStatusCode::internalServerError);
    }
}
