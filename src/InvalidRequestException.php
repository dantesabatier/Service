<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorDomain;

class InvalidRequestException extends InternalInconsistencyException
{
    public function __get(string $name)
    {
        if ($name === "error") {
            $this->$name = new Error(URLErrorDomain, $this->code, new Dictionary([LocalizedDescriptionKey => HTTPURLResponse::localizedString($this->code), LocalizedFailureReasonErrorKey => $this->message]));
            return $this->$name;
        }
        return parent::__get($name);
    }
}
