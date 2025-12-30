<?php

namespace Sabatier\Service;

/** @internal */
class JSONWebTokenVersionEvaluator implements AccessEvaluator
{
    public function evaluate(AccessEvaluationContext $context): bool
    {
        $authentication = $context->authentication;
        if (!($authentication instanceof BearerAuthentication)) {
            return true;
        }
        if (!($user = $authentication->authenticatedUser)) {
            return false;
        }
        if (!($payload = $authentication->token?->payload)) {
            return false;
        }
        return $user->version === $payload->ver;
    }
}
