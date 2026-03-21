<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/**
 * Base policy for securing managed object reads and writes.
 *
 * Provides shared authorization context and ownership enforcement,
 * while delegating read/write filtering to subclasses.
 */
abstract readonly class ManagedObjectSecurityPolicy
{
    /** @var Authorizable|null The authenticated user for policy evaluation. */
    public ?Authorizable $user;
    /** @var bool Whether the current authorization scopes include `own`. */
    public bool $hasOwnScope;
    /** @var bool Whether security enforcement is enabled for this request. */
    public bool $isSecurityEnabled;

    /**
     * @param Authorizable|null $user The authenticated user for authorization checks.
     * @param ArrayClass<string> $scopes The authorization scopes associated with the user.
     * @param bool $isSecurityEnabled Whether security enforcement is enabled for this request.
     */
    public function __construct(?Authorizable $user, ArrayClass $scopes, bool $isSecurityEnabled = true)
    {
        $this->user = $user;
        $this->hasOwnScope = $scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name));
        $this->isSecurityEnabled = $isSecurityEnabled;
    }

    /**
     * Enforces ownership rules for an object when `own` scope is present.
     * @param ManagedObject $object The managed object to check for ownership.
     */
    public function enforceOwnership(ManagedObject $object): void
    {
        if ($this->hasOwnScope && $this->isSecurityEnabled) {
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
