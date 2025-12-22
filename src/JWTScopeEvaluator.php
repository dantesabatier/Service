<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class JWTScopeEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        return $authentication->scopes->isEmpty || $authentication->scopes->containsElement(AuthenticationScopeAccess);
    }
}
