<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorNoPermissionsToReadFile;

class ConflictException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::conflict, error: new Error(URLErrorDomain, URLErrorNoPermissionsToReadFile, new Dictionary([LocalizedFailureReasonErrorKey => $this->getMessage() ?? HTTPURLResponse::localizedString(HTTPStatusCode::conflict)])));
    }
}
