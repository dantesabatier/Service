<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/** @internal */
final class JSONWebTokenEnabledEvaluator implements AuthenticationAccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        $authentication = $context->authentication;
        if ($authentication instanceof BearerAuthentication && ($authentication->token?->payload?->isEnabled === true)) {
            return true;
        }
        if (!($user = $authentication->authenticatedUser)) {
            return false;
        }
        return $user->isEnabled;
    }
}
