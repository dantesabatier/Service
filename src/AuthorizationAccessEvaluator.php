<?php

namespace Sabatier\Service;

/**
 * Marker interface for `AccessEvaluator` implementations that evaluate authorization conditions.
 *
 * Implementations assert that the authenticated user holds the required role or scope before
 * access is granted. The access evaluator chain uses this tag to distinguish authorization checks
 * from authentication checks so that the correct evaluator is invoked in the right phase of the
 * request lifecycle.
 *
 * @see AccessEvaluator
 * @see AccessEvaluatorChain
 */
interface AuthorizationAccessEvaluator extends AccessEvaluator
{
}
