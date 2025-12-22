<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/**
 * A chain of access evaluators that determines if a request is allowed to access protected content.
 */
final class AccessEvaluatorChain implements AccessEvaluator
{
    /**
     * @param ArrayClass<AccessEvaluator> $evaluators The ordered list of access evaluators.
     */
    public function __construct(public ArrayClass $evaluators)
    {
    }

    /**
     * Evaluates the chain of access rules for the given request.
     *
     * @param Request $request The request being evaluated.
     * @param Authentication $authentication The authentication object for the current request.
     * @param Session $session The session object associated with the request.
     * @param Dictionary $environment Environment variables relevant to the evaluation.
     * @param AuthorizationService $authorizationService The service responsible for authorization checks.
     * @param ManagedObjectContext $managedObjectContext The CoreData context used for persistent lookups.
     *
     * @return bool True if all evaluators pass, false if any evaluator fails.
     */
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        return $this->evaluators->allSatisfy(fn(AccessEvaluator $evaluator) => $evaluator->evaluate($request, $authentication, $session, $environment, $authorizationService, $managedObjectContext));
    }
}
