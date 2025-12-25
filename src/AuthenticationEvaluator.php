<?php

namespace Sabatier\Service;

use Override;

/** @internal */
final class AuthenticationEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        return $context->authentication->isValid;
    }
}
