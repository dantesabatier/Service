<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;

/**
 * Evaluates access by delegating the decision to a sequence of access evaluators.
 *
 * This evaluator implements a short-circuit AND semantic: all evaluators in the chain must independently allow access for the final result to be granted.
 * Evaluation stops as soon as an evaluator denies access, preventing unnecessary work and avoiding side effects in later evaluators.
 */
final class AccessEvaluatorChain implements AccessEvaluator
{
    /** @var AccessEvaluator|null The first evaluator that denied access during the most recent evaluation. */
    private(set) ?AccessEvaluator $failedEvaluator = null;

    /**
     * Creates a new evaluator chain that evaluates access in the order provided.
     *
     * @param ArrayClass<AccessEvaluator> $evaluators The ordered sequence of evaluators to execute. The chain grants access only if every evaluator in this list returns `true` when evaluating the given context. Evaluators should be pure in the sense that they must not alter the shared state in a way that compromises later evaluations.
     */
    public function __construct(public readonly ArrayClass $evaluators)
    {
    }

    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        $this->failedEvaluator = $this->evaluators->first(fn(AccessEvaluator $evaluator) => !$evaluator->evaluate($context));
        return $this->failedEvaluator === null;
    }
}
