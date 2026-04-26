<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/** @internal */
final class JSONWebTokenVersionEvaluator implements AuthenticationAccessEvaluator
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
        return $user->refreshTokenVersion === $payload->version;
    }
}
