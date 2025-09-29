<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Represents the default access policy enforcement mechanism.
 *
 * Ensures that access to protected content is gated by the conditions surrounding content availability and user authentication status.
 */
final class DefaultAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceAccess(): void
    {
        if ($this->responder->isProtectedContentAvailable || $this->authenticationService->isProtectedContentAvailable) {
            return;
        }
        if ($this->authenticationService->authentication->isValid) {
            throw new ForbiddenException(match ($this->responder->request->httpMethod) {
                HTTPRequestMethod::get => "You don't have permission to access this resource.",
                default => "You don't have permission to perform this action."
            });
        }
        throw new UnauthorizedException();
    }
}
