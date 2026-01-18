<?php

namespace Sabatier\Service;

use Override;

/** @internal */
final class JSONWebTokenVersionEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        $authentication = $context->authentication;
        if (!($authentication instanceof BearerAuthentication)) {
            return true;
        }
        if (!($payload = $authentication->token?->payload)) {
            return false;
        }
        if (!($user = $authentication->authenticatedUser)) {
            return false;
        }
        return $user->version === $payload->ver;
    }
}
