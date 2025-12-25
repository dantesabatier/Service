<?php

namespace Sabatier\Service;

use Override;

/** @internal */
final class SessionAuthenticationEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        if ($context->environment->offsetExists(JWTPrivateKey)) {
            return true;
        }
        return $context->session->isActive && $context->session->valueForKey(SessionAuthenticatedKey) === true && $context->session->valueForKey(SessionUserKey) !== null;
    }
}
