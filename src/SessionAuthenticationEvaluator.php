<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class SessionAuthenticationEvaluator implements AccessEvaluator
{
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        if ($environment->offsetExists(JWTPrivateKey)) {
            return true;
        }
        return $session->isActive && $session->valueForKey(SessionAuthenticatedKey) === true && $session->valueForKey(SessionUserKey) !== null;
    }
}
