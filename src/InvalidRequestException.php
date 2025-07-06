<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * Represents an exception thrown when an invalid request is encountered.
 */
class InvalidRequestException extends InternalInconsistencyException
{
    public Error $error {
        get => $this->error ??= new Error(ServiceErrorDomain, $this->code, new Dictionary([LocalizedDescriptionKey => HTTPURLResponse::localizedString($this->code), LocalizedFailureReasonErrorKey => $this->message]));
    }
}
