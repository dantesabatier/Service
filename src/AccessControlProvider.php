<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;

/**
 * Interface AccessControlProvider
 *
 * Provides a contract for implementing access control mechanisms.
 * This interface defines the necessary method to authorize access based
 * on the provided authorizable entity.
 */
interface AccessControlProvider
{
    /**
     * Checks and enforces the authorization of an entity for a specific action on a resource within a managed context.
     *
     * @param Authorizable $entity The entity being authorized.
     * @param string $resource The resource to be accessed or manipulated.
     * @param AuthorizationType $action The type of action to be performed on the resource.
     * @param ManagedObjectContext $context The contextual information for managing the authorization process.
     */
    public function authorize(Authorizable $entity, string $resource, AuthorizationType $action, ManagedObjectContext $context): void;
}
