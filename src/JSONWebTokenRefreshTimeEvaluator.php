<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Date;

/** @internal */
final class JSONWebTokenRefreshTimeEvaluator implements AuthenticationAccessEvaluator
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
        return !($payload->notBefore && $payload->notBefore > new Date()->timeIntervalSinceReferenceDate);
    }
}
