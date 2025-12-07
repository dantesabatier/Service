<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Represents the default access policy enforcement mechanism.
 */
final class DefaultAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceAccess(Responder $firstResponder, AuthenticationManager $authenticationManager): void
    {
        if ($firstResponder->isProtectedContentAvailable || $authenticationManager->isProtectedContentAvailable) {
            return;
        }
        if ($authenticationManager->authentication->isValid) {
            throw new ForbiddenException(match ($firstResponder->request->httpMethod) {
                HTTPRequestMethod::get => "You don't have permission to access this resource.",
                default => "You don't have permission to perform this action."
            });
        }
        throw new UnauthorizedException();
    }
}
