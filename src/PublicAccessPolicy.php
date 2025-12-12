<?php

namespace Sabatier\Service;

use Override;

/**
 * PublicAccessPolicy allows unconditional access to a resource.
 *
 * This policy intentionally bypasses both authentication and authorization checks. It is typically used for endpoints that must remain publicly accessible, such as login routes, health checks, documentation endpoints, or other non-restricted resources.
 */
final class PublicAccessPolicy extends AccessPolicy
{
    #[Override]
    public function enforceAccess(Responder $responder, AuthenticationManager $authenticationManager): void
    {
        // No access enforcement for public access policy
    }
}
