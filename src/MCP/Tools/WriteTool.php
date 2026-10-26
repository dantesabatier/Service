<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Closure;
use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Schema\EntitySchema;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use Sabatier\Service\ModifyPersistentSpaceOperation;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/**
 * @internal
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class WriteTool extends AbstractTool
{
    /** @var array<array-key, mixed> The argument naming the entity a write targets. */
    protected const array entityNameArgument = ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."];

    #[Override]
    public bool $isOpenWorld {
        get => false;
    }

    /**
     * The name of the entity a write targets, refused before anything reaches the store.
     *
     * @param Dictionary<mixed> $arguments
     */
    protected function entityName(Dictionary $arguments): string
    {
        $entityName = $arguments["entityName"];
        /** @noinspection PhpStrictTypeCheckingInspection */
        return is_string($entityName) && $entityName !== "" ? $entityName : fatal_error("entityName is required");
    }

    /**
     * @throws Exception
     */
    protected function entity(string $entityName): EntityDescription
    {
        return EntityDescription::entity($entityName, $this->context);
    }

    /**
     * Applies a domain change to a row the subject may write, for a tool whose change is a rule rather than a set of values.
     *
     * @template T of ManagedObject
     * @param Closure(T): void $modification
     * @param Dictionary<mixed>|null $serialization
     * @return Dictionary<mixed>
     * @throws Exception
     */
    protected function modify(EntityDescription $entity, ManagedObjectID|int|string $objectID, Closure $modification, ?Dictionary $serialization = null): Dictionary
    {
        return new ModifyPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $entity, $objectID, $modification, $serialization)->perform();
    }

    /**
     * Rejects an attempt to create a row for an abstract entity. Abstract entities have no table of their own and cannot be instantiated — a concrete sub-entity must be created instead.
     *
     * @throws Exception
     */
    protected function assertConcreteEntity(EntityDescription $entity): void
    {
        if (!$entity->isAbstract) {
            return;
        }
        $concrete = $entity->subentities->filter(fn(EntityDescription $subentity): bool => !$subentity->isAbstract)->map(fn(EntityDescription $subentity): string => $subentity->name);
        $hint = $concrete->isEmpty ? "It has no concrete sub-entities." : "Create one of its concrete sub-entities instead: {$concrete->join(", ")}.";
        fatal_error("\"$entity->name\" is an abstract entity and cannot be instantiated. $hint");
    }

    /**
     * @param Dictionary<mixed> $values
     * @return Dictionary<mixed>
     */
    protected function normalizeRelationships(string $entityName, Dictionary $values): Dictionary
    {
        $schema = $this->entitySchema($entityName);
        $normalized = clone $values;
        foreach ($values as $key => $value) {
            if ($schema->relationships[$key] && is_numeric($value)) {
                $normalized[$key] = new Dictionary([ManagedObjectObjectIDKey => $value]);
            }
        }
        return $normalized;
    }

    /**
     * Derives a serialization shape from the values passed to a create/update, so the response can echo back exactly the graph that was written — attributes as leaves, related objects (nested dictionaries, or arrays of them) recursed into — without an extra fetch. The shape is the structure of what was created, which is already known at write time.
     *
     * @param Dictionary<mixed> $values
     * @return Dictionary<mixed>
     */
    protected function shapeFromValues(string $entityName, Dictionary $values): Dictionary
    {
        $schema = $this->entitySchema($entityName);
        /** @var Dictionary<mixed> $shape */
        $shape = new Dictionary();
        foreach ($values as $key => $value) {
            $relationship = $schema->relationships[$key];
            if (!$relationship instanceof RelationshipSchema) {
                $shape[$key] = true;
                continue;
            }
            /** @var ArrayClass<mixed> $children */
            $children = $value instanceof Dictionary ? new ArrayClass([$value]) : ($value instanceof ArrayClass ? $value : new ArrayClass());
            /** @var Dictionary<mixed> $subShape */
            $subShape = new Dictionary();
            $children->forEach(function (mixed $child) use ($relationship, $subShape): void {
                if ($child instanceof Dictionary) {
                    $subShape->merge($this->shapeFromValues($relationship->target, $child));
                }
            });
            $shape[$key] = $subShape->isEmpty ? true : $subShape;
        }
        return $shape;
    }

    protected function entitySchema(string $name): EntitySchema
    {
        /** @var EntitySchema */
        return $this->descriptor->schema->entities[$name] ?? fatal_error("Unknown entity: \"$name\"");
    }
}
