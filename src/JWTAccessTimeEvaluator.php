<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;

/** @internal */
class JWTAccessTimeEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(Request $request, Authentication $authentication, Session $session, Dictionary $environment, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        assert($authentication instanceof BearerAuthentication);
        if (!($token = $authentication->token)) {
            return false;
        }
        $payload = $token->payload;
        $now = new Date()->timeIntervalSinceReferenceDate;
        if ($payload->nbf && $payload->nbf > $now) {
            return false;
        }
        if ($payload->exp && $payload->exp < $now) {
            return false;
        }
        return true;
    }
}
