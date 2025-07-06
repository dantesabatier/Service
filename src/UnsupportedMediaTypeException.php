<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPStatusCode;

/**
 * Exception thrown when a request contains a media type not supported by the server.
 */
class UnsupportedMediaTypeException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::unsupportedMediaType);
    }
}
