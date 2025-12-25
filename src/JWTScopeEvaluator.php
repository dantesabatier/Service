<?php

namespace Sabatier\Service;

use Override;

/** @internal */
final readonly class JWTScopeEvaluator implements AccessEvaluator
{
    public function __construct(private string $requiredScope)
    {
    }

    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        return $context->authentication->technicalScopes->isEmpty || $context->authentication->technicalScopes->containsElement($this->requiredScope);
    }
}
