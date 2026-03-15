<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Set;

/** @internal */
final class PreflightResponder extends Responder
{
    #[Override]
    public Set $decorators {
        get => $this->decorators ??= new Set([ResponseHeaderSanitizerDecorator::class]);
    }

    public function respondToPreflightIfNeeded(): void
    {
        if (!$this->request->isPreflight) {
            return;
        }
        $this->response->send();
    }
}
