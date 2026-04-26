<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/** @internal */
final class AuthenticationEvaluator implements AuthenticationAccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        return $context->authentication->isValid;
    }
}
