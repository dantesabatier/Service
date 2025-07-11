<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;

/** @internal */
class DefaultAuthorizationService implements AuthorizationService
{
    public function authorize(Authorizable $entity, string $resource, AuthorizationType $action, ManagedObjectContext $context): void
    {
    }
}
