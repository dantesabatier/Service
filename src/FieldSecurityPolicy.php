<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
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
     * Both the rule's `by` roles and its `scope` are enforced here. A rule scoped to `own` narrows
     * the fetch to the subject's own rows, independently of the request's `own` authorization scope —
     * the attribute is a second, declarative source of the same restriction, so the two may overlap
     * and the narrowing is simply applied twice.
     *
     * Returns null when there is nothing to narrow: security is disabled, the class carries no
     * `#[Readable]`, or the subject's roles satisfy a rule that declares neither `where` nor `own`.
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
        $predicates = new ArrayClass([
            $rule->where !== null ? $this->conditionResolver->predicate($rule->where, $rule->arguments) : null,
            $rule->requiresOwner ? $this->resourceOwnershipPredicate($className) : null,
        ])->filter(fn(?Predicate $predicate): bool => $predicate !== null);
        if ($predicates->isEmpty) {
            return null;
        }
        return $predicates->count > 1 ? CompoundPredicate::andPredicateWithSubpredicates($predicates) : $predicates->first;
    }

    /**
     * Builds the predicate narrowing a resource scoped to `own` down to the subject's own rows.
     *
     * Returns an unsatisfiable predicate when the restriction cannot be expressed — no authenticated
     * subject to compare against, or a class that declares no `#[Owner]` field. A scope that cannot
     * be applied must not read as no restriction at all, so it closes rather than opens.
     *
     * @param class-string<ManagedObject> $className The managed object class backing the resource.
     * @throws Exception
     */
    private function resourceOwnershipPredicate(string $className): Predicate
    {
        $ownerKey = OwnerResolver::getOwnerFieldName($className);
        if (!$this->user || $ownerKey === null) {
            error_log("Readable on $className is scoped to own but the restriction cannot be expressed; the fetch is narrowed to no rows.");
            return Predicate::value(false);
        }
        return new ComparisonPredicate(Expression::expressionForKeyPath($ownerKey), Expression::expressionForConstantValue($this->user));
    }

    /**
     * Enforces the field-level `#[Readable]` declared on a single field of a class, for a caller that
     * reads that field without materializing the row it belongs to — an aggregate or a grouping reads
     * the column straight out of the database, so {@see self::applySecureRead()} never sees it.
     *
     * A rule this caller cannot satisfy is denied rather than filtered out: the column *is* the
     * result, so dropping it would leave nothing to return. Because there is no row to evaluate
     * against, a rule carrying a `where` condition or scoped to `own` cannot be checked and is
     * refused outright — the same way an inexpressible restriction closes on the read side.
     *
     * @param class-string<ManagedObject> $className The managed object class declaring the field.
     * @param string $fieldName The field being read.
     * @param string $keyPath The key path as the caller spelled it, for the error message.
     * @throws ForbiddenException When the subject may not read the field.
     * @throws Exception
     */
    public function enforceFieldRead(string $className, string $fieldName, string $keyPath): void
    {
        if (!$this->isSecurityEnabled) {
            return;
        }
        $rule = FieldSecurityFilter::rule($className, Readable::class, $fieldName);
        if (!$rule) {
            return;
        }
        $rule->allowsRoles($this->userRoles) && $rule->where === null && !$rule->requiresOwner
            ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to read \"%s\"."), $keyPath));
    }

    /**
     * Enforces the resource-level write rule declared by a `#[Writable]` attribute on the object's class.
     *
     * A rule scoped to `own` requires the subject to own the row, independently of the request's `own`
     * authorization scope — the attribute is a second, declarative source of the same restriction. An
     * unowned row does not satisfy it: unlike {@see self::enforceOwnership()}, which treats a missing
     * owner as nothing to enforce, a rule that explicitly asks for `own` is denied when ownership
     * cannot be established.
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
        $allowed = $rule->allowsRoles($this->userRoles)
            && ($rule->where === null || $this->evaluateCondition($rule->where, $rule->arguments, $object))
            && (!$rule->requiresOwner || $this->ownsResource($object));
        $allowed ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to modify this \"%s\" resource."), $object->entity->name));
    }

    /**
     * Returns whether the subject owns the row a resource rule scoped to `own` targets.
     *
     * Requires an owner to compare against: a row whose `#[Owner]` field is empty, or a class that
     * declares none, is not owned by anybody and so fails a scope that explicitly demands ownership.
     *
     * @param ManagedObject $object The managed object being written.
     * @throws Exception
     */
    private function ownsResource(ManagedObject $object): bool
    {
        $owner = new OwnerResolver($object)->owner;
        return $owner !== null && $this->user?->isEqual($owner);
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
