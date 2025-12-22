<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

final class JWTScopeEvaluator implements AccessEvaluator
{
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        return $authentication->scopes->isEmpty || $authentication->scopes->containsElement(AuthenticationScopeAccess);
    }
}
