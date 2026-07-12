<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use JsonException;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Service\AccessConditionResolver;
use Sabatier\Service\Application;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\DefaultAccessPolicy;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\FieldSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\AttributeSchema;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\OwnerResolver;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\localized_string;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/**
 * Base class for all MCP tools, both built-in and custom.
 *
 * Extend this class and place the subclass in the application's `MCPTools`
 * directory to register a custom tool with the MCP server.
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class AbstractTool
{
    abstract public string $name {
        get;
    }
    /** @var string|null Human-readable display name; defaults to vocabulary `title` or falls back to `name`. */
    public ?string $title {
        get => $this->vocabulary->localize($this->name, "title");
    }
    /** @var string Description read by the LLM client; defaults to vocabulary `description` or falls back to `name`. */
    public string $description {
        get => $this->vocabulary->localize($this->name, "description") ?? $this->name;
    }
    abstract public array $inputSchema {
        get;
    }
    /** @var ToolVocabulary The vocabulary of the bundle that owns this concrete tool class. */
    private ToolVocabulary $vocabulary {
        get => $this->vocabulary ??= ToolVocabulary::forBundle(Bundle::bundleForClass(static::class));
    }
    /** @var bool Whether security enforcement is enabled for this request. */
    protected bool $isSecurityEnabled {
        get => Application::shared()->accessPolicy instanceof DefaultAccessPolicy;
    }
    /** @var AuthorizationContext Authorization context derived from the current authentication. */
    protected AuthorizationContext $authorizationContext {
        get => $this->authorizationContext ??= new AuthorizationContext(Application::shared()->authenticationManager->authentication->authenticatedUser, Application::shared()->authenticationManager->authentication->authorizationScopes, $this->isSecurityEnabled);
    }
    /** @var FieldSecurityPolicy Security policy used for field-level read/write enforcement. */
    protected FieldSecurityPolicy $fieldSecurityPolicy {
        get => $this->fieldSecurityPolicy ??= new FieldLevelSecurityPolicy($this->authorizationContext);
    }
    /** @var Authorizable|null The authenticated user for policy evaluation. */
    protected ?Authorizable $user {
        get => $this->fieldSecurityPolicy->user;
    }

    public function __construct(protected readonly ManagedObjectContext $context, protected readonly ModelDescriptor $descriptor)
    {
    }

    /** @return ArrayClass<ContentItem> */
    abstract public function execute(Dictionary $arguments): ArrayClass;

    /**
     * Builds the ownership predicate for an entity when the caller's `own` scope applies —
     * the same guard `ReadPersistentSpaceResponseStrategy` uses to scope GET fetches. Returns
     * `null` when security is disabled, the scope is absent, or the entity declares no
     * `#[Owner]` field.
     * @throws Exception
     */
    protected function ownershipPredicate(EntityDescription $entity): ?Predicate
    {
        $entityClassName = $entity->managedObjectClassName ?? $entity->name;
        if ($this->isSecurityEnabled && $this->fieldSecurityPolicy->hasOwnScopeFor($entity->name) && class_exists($entityClassName) && ($ownerKey = OwnerResolver::getOwnerFieldName($entityClassName))) {
            return new ComparisonPredicate(Expression::expressionForKeyPath($ownerKey), Expression::expressionForConstantValue($this->user));
        }
        return null;
    }

    /**
     * Scopes a fetch request to rows owned by the current user when the `own` scope applies,
     * AND-combining with any predicate already on the request.
     * @throws Exception
     */
    protected function applyOwnershipScope(FetchRequest $request): void
    {
        $entity = $request->entity;
        if (!$entity instanceof EntityDescription || !($ownershipPredicate = $this->ownershipPredicate($entity))) {
            return;
        }
        $request->predicate = $request->predicate ? CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass([$request->predicate, $ownershipPredicate])) : $ownershipPredicate;
    }

    /**
     * @throws ForbiddenException
     */
    protected function enforceOwnership(ManagedObject $object): void
    {
        $this->fieldSecurityPolicy->enforceOwnership($object);
    }

    /**
     * @param Dictionary<mixed> $body
     * @throws Exception
     */
    protected function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $this->fieldSecurityPolicy->applySecureUpdate($object, $body);
    }

    /**
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    protected function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $this->fieldSecurityPolicy->applySecureRead($object, $data);
    }

    /**
     * Enforces per-entity RBAC for the resource this tool call targets — the check
     * `AuthorizationEvaluator` performs by URL for regular endpoints, which never sees the
     * entity an MCP tool operates on because the request URL is always `/mcp`. Consults the
     * token scopes, the authorization caches and the database, in that order.
     * @throws Exception
     */
    protected function enforceEntityAuthorization(string $resource, AuthorizationType $action): void
    {
        if (!$this->isSecurityEnabled) {
            return;
        }
        $user = $this->user ?? throw new ForbiddenException(localized_string("You must be authenticated to perform this action."));
        Application::shared()->authorizationService->isAuthorized($user, $resource, $action, $this->fieldSecurityPolicy->scopes, $this->context) ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to %s \"%s\"."), $action->name, $resource));
    }

    protected function entity(string $name): EntitySchema
    {
        /** @var EntitySchema */
        return $this->descriptor->schema->entities[$name] ?? fatal_error("Unknown entity: \"$name\"");
    }

    protected function validateKeyPath(string $entityName, string $keyPath): void
    {
        $parts = explode(".", $keyPath);
        $current = $this->entity($entityName);
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($index === $last) {
                if (!$current->attributes[$part] && !$current->relationships[$part]) {
                    fatal_error("Unknown property \"$part\" on $current->name. Attributes: {$current->attributes->keys}. Relationships: {$current->relationships->keys}.");
                }
                return;
            }
            /** @var RelationshipSchema $relationship */
            $relationship = $current->relationships[$part] ?? fatal_error("\"$part\" is not a relationship on $current->name. Relationships: {$current->relationships->keys}.");
            $current = $this->entity($relationship->target);
        }
    }

    /**
     * @param string $entityName
     * @param string $format
     * @param ArrayClass<string> $arguments
     */
    protected function validatePredicateKeyPaths(string $entityName, string $format, ArrayClass $arguments): void
    {
        preg_match_all("/%[K@sdf]/", $format, $matches);
        $placeholders = new ArrayClass($matches[0]);
        $arguments->count === $placeholders->count ?: $arguments
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("Invalid predicate: %d argument(s) provided but %d placeholder(s) found in \"%s\". Each placeholder (%s) requires exactly one argument in the same position.\n%s", $arguments->count, $placeholders->count, $format, $placeholders->join(", "), $x))
                |> fatal_error(...);
        foreach ($placeholders as $index => $placeholder) {
            if ($placeholder !== "%K") {
                continue;
            }
            $keyPath = $arguments[$index];
            $this->validateKeyPath($entityName, $keyPath);
            $attribute = $this->resolveAttribute($entityName, $keyPath);
            if (!$attribute?->enum) {
                continue;
            }
            if ($arguments->count <= $index + 1) {
                continue;
            }
            $value = $arguments[$index + 1];
            /** @var ArrayClass<string|int> $cases */
            $cases = $attribute?->enum->cases ?? new Dictionary();
            $invalid = $value instanceof ArrayClass ? $value->filter(fn(mixed $v): bool => !$cases->containsElement($v)) : ($cases->containsElement($value) ? new ArrayClass() : new ArrayClass([$value]));
            if ($invalid->isEmpty) {
                continue;
            }
            $map = $cases->map(fn(string|int $v, int $k): string => "\"$k\" → $v")->join(", ");
            fatal_error(sprintf("Invalid enum value for \"%s\": %s. Pass the mapped value, not the case name. Cases: %s", $keyPath, $invalid->description, $map));
        }
    }

    private function resolveAttribute(string $entityName, string $keyPath): ?AttributeSchema
    {
        $parts = explode(".", $keyPath);
        $current = $this->entity($entityName);
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($index === $last) {
                return $current->attributes[$part] ?? null;
            }
            /** @var RelationshipSchema|null $relationship */
            $relationship = $current->relationships[$part] ?? null;
            if (!$relationship) {
                return null;
            }
            $current = $this->entity($relationship->target);
        }
        return null;
    }

    protected function fetchRequest(string $entityName): FetchRequest
    {
        $model = $this->context->persistentStoreCoordinator?->managedObjectModel ?? fatal_error("No managed object model");
        $entity = $model->entitiesByName[$entityName] ?? fatal_error("Unknown entity: $entityName");
        $request = new FetchRequest();
        $request->entity = $entity;
        return $request;
    }

    /**
     * Rejects an attempt to create a row for an abstract entity. Abstract entities have
     * no table of their own and cannot be instantiated — a concrete sub-entity must be
     * created instead. Call this before inserting.
     */
    protected function assertConcreteEntity(string $entityName): void
    {
        $entity = EntityDescription::entity($entityName, $this->context);
        if (!$entity->isAbstract) {
            return;
        }
        $concrete = $entity->subentities->filter(fn(EntityDescription $subentity): bool => !$subentity->isAbstract)->map(fn(EntityDescription $subentity): string => $subentity->name);
        $hint = $concrete->isEmpty ? "It has no concrete sub-entities." : "Create one of its concrete sub-entities instead: {$concrete->join(", ")}.";
        fatal_error("\"$entityName\" is an abstract entity and cannot be instantiated. $hint");
    }

    protected function buildPredicate(string $format, ArrayClass $arguments): Predicate
    {
        return Predicate::format($format, $arguments) ?? fatal_error("Invalid predicate format");
    }

    protected function normalizeRelationships(string $entityName, Dictionary $values): Dictionary
    {
        $schema = $this->entity($entityName);
        $normalized = clone $values;
        foreach ($values as $key => $value) {
            if ($schema->relationships[$key] && is_numeric($value)) {
                $normalized[$key] = new Dictionary([ManagedObjectObjectIDKey => $value]);
            }
        }
        return $normalized;
    }

    /**
     * Derives a serialization shape from the values passed to a create/update, so the response can
     * echo back exactly the graph that was written — attributes as leaves, related objects (nested
     * dictionaries, or arrays of them) recursed into — without an extra fetch. The shape is the
     * structure of what was created, which is already known at write time.
     *
     * @param string $entityName
     * @param Dictionary<mixed> $values
     * @return Dictionary<mixed>
     */
    protected function shapeFromValues(string $entityName, Dictionary $values): Dictionary
    {
        $schema = $this->entity($entityName);
        /** @var Dictionary<mixed> $shape */
        $shape = new Dictionary();
        foreach ($values as $key => $value) {
            $relationship = $schema->relationships[$key] ?? null;
            if (!$relationship instanceof RelationshipSchema) {
                $shape[$key] = true;
                continue;
            }
            /** @var ArrayClass<mixed> $children */
            $children = $value instanceof Dictionary ? new ArrayClass([$value]) : ($value instanceof ArrayClass ? $value : new ArrayClass());
            $subShape = $children->reduce(new Dictionary(), fn(Dictionary $carry, mixed $child): Dictionary => $child instanceof Dictionary ? $carry->merging($this->shapeFromValues($relationship->target, $child)) : $carry);
            $shape[$key] = $subShape->isEmpty ? true : $subShape;
        }
        return $shape;
    }

    /**
     * Resolves the temporal substitution tokens in a predicate arguments array to literal date strings.
     * Handles one level of nesting (e.g. BETWEEN ["$WEEK_START","$WEEK_END"]).
     *
     * @param ArrayClass<mixed> $params
     * @return ArrayClass<mixed>
     */
    protected function resolveVariables(ArrayClass $params): ArrayClass
    {
        $dateMappings = new AccessConditionResolver()->variables;
        $resolve = fn(mixed $v): mixed => is_string($v) && $dateMappings[$v] !== null ? $dateMappings[$v] : $v;
        return $params->map(fn(mixed $value): mixed => $value instanceof ArrayClass ? $value->map($resolve) : $resolve($value));
    }

    /** @return ArrayClass<ContentItem> */
    protected function textResult(string $text): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", $text)]);
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    protected function jsonResult(mixed $data): ArrayClass
    {
        return $this->textResult(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
