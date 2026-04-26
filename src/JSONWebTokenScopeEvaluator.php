<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/** @internal */
final readonly class JSONWebTokenScopeEvaluator implements AuthenticationAccessEvaluator
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
