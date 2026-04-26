<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\localized_string;

/**
 * DefaultAccessPolicy enforces authentication and authorization for protected resources.
 *
 * This policy applies the standard access-control behavior used across most endpoints:
 *
 * - If either the responder or authentication manager indicates that protected content is available, the request is allowed without further checks.
 *
 * - If the user is authenticated but lacks the necessary permissions, a {@see ForbiddenException} is thrown. The exception message varies depending on the HTTP method to provide clearer intent (access vs. action).
 *
 * - If the user is not authenticated, a {@see UnauthorizedException} is thrown.
 */
final class DefaultAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceAccess(Responder $responder, AuthenticationManager $authenticationManager): void
    {
        if ($responder->isProtectedContentAvailable || $authenticationManager->isProtectedContentAvailable) {
            return;
        }
        $failedEvaluator = $authenticationManager->accessEvaluator instanceof AccessEvaluatorChain ? $authenticationManager->accessEvaluator->failedEvaluator : null;
        if ($failedEvaluator instanceof AuthorizationAccessEvaluator) {
            throw new ForbiddenException(match ($responder->request->httpMethod) {
                HTTPRequestMethod::get => localized_string("You don't have permission to access this resource."),
                default => localized_string("You don't have permission to perform this action."),
            });
        }
        $message = $failedEvaluator instanceof AccessEvaluator ? $failedEvaluator
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("Access denied: evaluator %s rejected the request.", $x))
                |> localized_string(...) : localized_string("Access denied: no evaluator allowed access.");
        throw new UnauthorizedException($message);
    }
}
