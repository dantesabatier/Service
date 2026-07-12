<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\localized_string;

/**
 * Base policy for field-level security on managed object reads and writes.
 *
 * Provides shared authorization context and ownership enforcement,
 * while delegating read/write filtering to subclasses. It is also the single
 * point that resolves attribute-based conditions (`where`) into predicates,
 * so query-time, row-level, and field-level consumers agree on their meaning.
 */
abstract readonly class FieldSecurityPolicy
{
    /** @var Authorizable|null The authenticated user for policy evaluation. */
    public ?Authorizable $user;
    /** @var bool Whether security enforcement is enabled for this request. */
    public bool $isSecurityEnabled;
    /** @var ArrayClass<string> The authorization scopes for the current request. */
    public ArrayClass $scopes;
    /** @var Dictionary<mixed> The request environment bound to `$ENVIRONMENT` in attribute-based conditions. */
    public Dictionary $environment;
    /** @var AccessConditionResolver Resolves `where` conditions into predicates with `$SUBJECT`/`$ENVIRONMENT` substituted. */
    public AccessConditionResolver $conditionResolver;

    /**
     * @param AuthorizationContext $authorizationContext The authorization context for the current request.
     */
    public function __construct(AuthorizationContext $authorizationContext)
    {
        $this->user = $authorizationContext->user;
        $this->scopes = $authorizationContext->scopes;
        $this->isSecurityEnabled = $authorizationContext->isSecurityEnabled;
        $this->environment = $authorizationContext->environment;
        $this->conditionResolver = new AccessConditionResolver($this->user, $this->environment);
    }

    /**
     * Evaluates an attribute-based condition against a managed object, supplying `$SUBJECT`/`$ENVIRONMENT` as the substitution context.
     *
     * @param string $where The predicate format string.
     * @param list<mixed> $arguments Positional arguments for the format placeholders.
     * @param ManagedObject $object The managed object to evaluate the condition against.
     */
    public function evaluateCondition(string $where, array $arguments, ManagedObject $object): bool
    {
        return $this->conditionResolver->evaluate($where, $arguments, $object);
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
            $service->isOwner ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to modify this \"%s\" row: it belongs to another user."), $object->entity->name));
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
