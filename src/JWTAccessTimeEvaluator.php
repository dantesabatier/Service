<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Date;

/** @internal */
class JWTAccessTimeEvaluator implements AccessEvaluator
{
    #[Override]
    public function evaluate(AccessEvaluationContext $context): bool
    {
        if (!$context->authentication instanceof BearerAuthentication) {
            return true;
        }
        if (!($payload = $context->authentication->token?->payload)) {
            return false;
        }
        $now = new Date()->timeIntervalSinceReferenceDate;
        if ($payload->nbf && $payload->nbf > $now) {
            return false;
        }
        return !($payload->exp && $payload->exp < $now);
    }
}
