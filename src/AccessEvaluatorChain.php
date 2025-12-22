<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

final class AccessEvaluatorChain implements AccessEvaluator
{
    /**
     * @param ArrayClass<AccessEvaluator> $evaluators
     */
    public function __construct(public ArrayClass $evaluators)
    {
    }

    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        return $this->evaluators->allSatisfy(fn(AccessEvaluator $evaluator) => $evaluator->evaluate($request, $authentication, $session, $environment, $authorizationService, $managedObjectContext));
    }
}
