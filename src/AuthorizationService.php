<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;

/**
 * Service interface responsible for handling authorization logic.
 */
interface AuthorizationService
{
    /**
     * Authorizes the given entity to perform the specified action on the specified resource.
     *
     * @param Authorizable $entity The entity being authorized.
     * @param string $resource The resource to be accessed or manipulated.
     * @param AuthorizationType $action The type of action to be performed on the resource.
     * @param ManagedObjectContext $context The contextual information for managing the authorization process.
     */
    public function authorize(Authorizable $entity, string $resource, AuthorizationType $action, ManagedObjectContext $context): void;
}
