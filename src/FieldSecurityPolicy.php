<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Set;
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
    /** @var AccessConditionResolver Resolves `where` conditions into predicates with temporal substitution variables. */
    public AccessConditionResolver $conditionResolver;
    /** @var Set<string> The authenticated subject's role names, empty when unauthenticated. */
    protected Set $userRoles;

    /**
     * @param AuthorizationContext $authorizationContext The authorization context for the current request.
     */
    public function __construct(AuthorizationContext $authorizationContext)
    {
        $this->user = $authorizationContext->user;
        $this->scopes = $authorizationContext->scopes;
        $this->isSecurityEnabled = $authorizationContext->isSecurityEnabled;
        $this->conditionResolver = new AccessConditionResolver();
        $this->userRoles = $this->user ? $this->user->roles->map(fn(AuthorizableRole $role): string => $role->name) : new Set();
    }

    /**
     * Evaluates an attribute-based condition against a managed object, supplying the temporal substitution variables as the substitution context.
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
     * Resolves the read predicate declared by a `#[Readable]` attribute on a managed object class,
     * to be AND-folded into the fetch the same way `#[Owner]` narrows a read by owner.
     *
     * A resource-level rule gates the row rather than a field, so a subject the rule excludes reads
     * nothing: a listing omits every row and a read by id yields none. That exclusion is expressed as
     * an unsatisfiable predicate rather than a thrown error, both because it composes with whatever
     * else narrows the fetch and because it keeps the restricted rows indistinguishable from absent
     * ones. The counterpart on the write side is {@see self::enforceResourceAccess()}, which does
     * throw — a write names the row it targets, so denying it cannot be expressed as an empty result.
     *
     * The rule's `by` roles are enforced here; its `scope` is not, because ownership narrowing on
     * read is already driven by the request's `own` authorization scope.
     *
     * Returns null when there is nothing to narrow: security is disabled, the class carries no
     * `#[Readable]`, or the subject's roles satisfy a rule that declares no `where`.
     *
     * @param class-string<ManagedObject> $className The managed object class backing the resource.
     * @throws Exception
     */
    public function resourceReadPredicate(string $className): ?Predicate
    {
        if (!$this->isSecurityEnabled) {
            return null;
        }
        $rule = ResourceRule::resolve($className, Readable::class);
        if (!$rule) {
            return null;
        }
        if (!$rule->allowsRoles($this->userRoles)) {
            error_log("Readable on $className excludes the subject's roles; the fetch is narrowed to no rows.");
            return Predicate::value(false);
        }
        return $rule->where !== null ? $this->conditionResolver->predicate($rule->where, $rule->arguments) : null;
    }

    /**
     * Enforces the resource-level write rule declared by a `#[Writable]` attribute on the object's class.
     *
     * @param ManagedObject $object The managed object being created, updated, or deleted.
     * @throws ForbiddenException When the subject may not write the resource.
     * @throws Exception
     */
    public function enforceResourceAccess(ManagedObject $object): void
    {
        if (!$this->isSecurityEnabled) {
            return;
        }
        $rule = ResourceRule::resolve($object::class, Writable::class);
        if (!$rule) {
            return;
        }
        $allowed = $rule->allowsRoles($this->userRoles) && ($rule->where === null || $this->evaluateCondition($rule->where, $rule->arguments, $object));
        $allowed ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to modify this \"%s\" resource."), $object->entity->name));
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
