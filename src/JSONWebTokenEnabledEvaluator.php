<?php

namespace Sabatier\Service;

use Override;

/** @internal */
final class JSONWebTokenEnabledEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        $authentication = $context->authentication;
        if ($authentication instanceof BearerAuthentication && ($payload = $authentication->token?->payload) && ($payload->isEnabled === true)) {
            return true;
        }
        if (!($user = $authentication->authenticatedUser)) {
            return false;
        }
        return $user->isEnabled;
    }
}
