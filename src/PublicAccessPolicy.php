<?php

namespace Sabatier\Service;

use Override;

/**
 * A policy that defines public access to resources bypassing checks and enforcement of protected content.
 *
 * Extends the AccessPolicy base class to provide implementation where all requests are allowed unrestricted access.
 */
final class PublicAccessPolicy extends AccessPolicy
{
    #[Override]
    public function isAuthorizationRequired(Request $request): bool
    {
        return false;
    }

    #[Override]
    public function enforceAccess(Request $request, Responder $responder, AuthenticationService $authenticationService): void
    {
    }
}
