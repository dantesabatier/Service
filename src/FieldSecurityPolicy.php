<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/**
 * Base policy for field-level security on managed object reads and writes.
 *
 * Provides shared authorization context and ownership enforcement,
 * while delegating read/write filtering to subclasses.
 */
abstract readonly class FieldSecurityPolicy
{
    /** @var Authorizable|null The authenticated user for policy evaluation. */
    public ?Authorizable $user;
    /** @var bool Whether security enforcement is enabled for this request. */
    public bool $isSecurityEnabled;
    /** @var ArrayClass<string> The authorization scopes for the current request. */
    public ArrayClass $scopes;

    /**
     * @param AuthorizationContext $authorizationContext The authorization context for the current request.
     */
    public function __construct(AuthorizationContext $authorizationContext)
    {
        $this->user = $authorizationContext->user;
        $this->scopes = $authorizationContext->scopes;
        $this->isSecurityEnabled = $authorizationContext->isSecurityEnabled;
    }

    /**
     * Returns whether the current authorization scopes include `own` for the given entity.
     * @param string $entityName The entity name to check ownership scope for.
     */
    public function hasOwnScopeFor(string $entityName): bool
    {
        $ownSuffix = ":" . AuthorizationScope::own->name;
        return $this->scopes->contains(fn(string $scope): bool => str_starts_with($scope, "$entityName:") && str_ends_with($scope, $ownSuffix));
    }

    /**
     * Enforces ownership rules for an object when `own` scope is present for its entity.
     * @param ManagedObject $object The managed object to check for ownership.
     */
    public function enforceOwnership(ManagedObject $object): void
    {
        if ($this->isSecurityEnabled && $this->hasOwnScopeFor($object->entity->name)) {
            $service = new OwnershipService(new OwnerResolver($object), $this->user ?? fatal_error());
            $service->isOwner ?: throw new ForbiddenException();
        }
    }

    /**
     * Applies secure write filtering and updates the object.
     * @param ManagedObject $object The managed object being updated.
     * @param Dictionary<mixed> $body The incoming payload to apply.
     * @throws Exception
     */
    abstract public function applySecureUpdate(ManagedObject $object, Dictionary $body): void;

    /**
     * Applies secure read filtering to the returned data.
     * @param ManagedObject $object The managed object being read.
     * @param Dictionary<mixed> $data The serialized data to filter.
     * @return Dictionary<mixed>
     * @throws Exception
     */
    abstract public function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary;
}
