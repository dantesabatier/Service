<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFileDoesNotExist;

class NotFoundException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::notFound, error: new Error(URLErrorDomain, URLErrorFileDoesNotExist, new Dictionary([LocalizedFailureReasonErrorKey => $this->getMessage() ?? HTTPURLResponse::localizedString(HTTPStatusCode::notFound)])));
    }
}
