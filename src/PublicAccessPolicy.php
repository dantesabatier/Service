<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;

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

    #[Override]
    public function allowsAccess(string $resource, AuthorizationType $action, ?Authorizable $user, ArrayClass $scopes, AuthorizationService $authorizationService, ManagedObjectContext $managedObjectContext): bool
    {
        return true;
    }
}
