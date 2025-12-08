<?php

namespace Sabatier\Service;

use Override;

/**
 * A policy that defines public access to resources bypassing checks and enforcement of protected content.
 */
final class PublicAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceAccess(Responder $responder, AuthenticationManager $authenticationManager): void
    {
        // No access enforcement for public access policy
    }
}
