<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Date;

/** @internal */
final class JSONWebTokenRefreshTimeEvaluator implements AccessEvaluator
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
        $now = new Date()->timeIntervalSinceReferenceDate;
        if ($payload->exp && $payload->exp > $now) {
            return false;
        }
        return !($payload->nbf && $payload->nbf > $now);
    }
}
