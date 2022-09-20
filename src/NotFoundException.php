<?php

namespace Sabatier\Service;

use Sabatier\Foundation\HTTPStatusCode;

/**
 * Class NotFoundException
 * @package Sabatier\Service
 */
class NotFoundException extends InvalidRequestException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message, HTTPStatusCode::notFound);
    }
}
