<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Marker interface for `AccessEvaluator` implementations that evaluate authentication conditions.
 *
 * Implementations assert that the incoming request is authenticated (e.g. carries a valid session
 * or bearer token) before access is granted. The access evaluator chain uses this tag to distinguish
 * authentication checks from authorization checks so that the correct evaluator is invoked
 * in the right phase of the request lifecycle.
 *
 * @see AccessEvaluator
 * @see AccessEvaluatorChain
 */
interface AuthenticationAccessEvaluator extends AccessEvaluator
{
}
