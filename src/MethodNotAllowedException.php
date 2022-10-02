<?php

namespace Sabatier\Service;

use Sabatier\Foundation\HTTPStatusCode;

/**
 * Class MethodNotAllowed
 * @package Sabatier\Service
 */
class MethodNotAllowedException extends InvalidRequestException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message, HTTPStatusCode::methodNotAllowed);
    }
}
