<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;

/**
 * Defines a contract for evaluating whether a request is permitted to access a resource.
 *
 * Implementations of this interface inspect the provided evaluation context to determine if entry should be granted. The evaluation may incorporate authentication state, authorization rules, environment information, and persisted identity data.
 */
interface AccessEvaluator
{
    /**
     * Determines whether access should be granted based on the supplied context.
     *
     * @param AccessEvaluationContext $context The fully populated evaluation context describing the current request, caller identity, environment, authorization services, and persistence source used to make the access decision.
     * @return bool true if access is allowed, false otherwise.
     * @throws Exception Thrown when evaluation cannot be completed due to an error, such as issues retrieving persisted permission data or determining authenticated identity.
     */
    public function evaluate(AccessEvaluationContext $context): bool;
}
