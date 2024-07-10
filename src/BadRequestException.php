<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorDomain;

class BadRequestException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::badRequest, error: new Error(URLErrorDomain, URLErrorBadURL, new Dictionary([LocalizedFailureReasonErrorKey => $this->getMessage() ?? HTTPURLResponse::localizedString($this->getCode())])));
    }
}
