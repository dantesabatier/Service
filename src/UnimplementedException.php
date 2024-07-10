<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorDomain;

class UnimplementedException extends InvalidRequestException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, HTTPStatusCode::unimplemented, error: new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedFailureReasonErrorKey => $this->getMessage() ?? HTTPURLResponse::localizedString($this->getCode())])));
    }
}
