<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
final class PreflightResponder extends Responder
{
    /** @var ArrayClass<string> */
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options]);
    }

    public function respondToPreflightIfNeeded(): void
    {
        if (!$this->request->isPreflight) {
            return;
        }
        $this->response->send();
    }
}
