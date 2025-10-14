<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;

/** @internal */
class DefaultAuthorizationService implements AuthorizationService
{
    #[Override]
    public function authorize(Authorizable $entity, string $resource, AuthorizationType $action, ManagedObjectContext $context): void
    {
    }
}
