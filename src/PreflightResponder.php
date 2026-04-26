<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @internal */
final class PreflightResponder extends Responder
{
    public function respondToPreflightIfNeeded(): void
    {
        if (!$this->request->isPreflight) {
            return;
        }
        $this->response->send();
    }
}
