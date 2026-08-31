<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\RelationshipSchema;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class FetchTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "fetch";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }

    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string", "description" => "Required. Entity name from the data model — call describe_model first if unsure."],
                "predicate" => ["type" => "string", "description" => "NSPredicate format string, e.g. \"%K == %@\". %K for key paths, %@ for strings/objects, %d for integers, %f for floats."],
                "arguments" => ["type" => "array", "items" => ["type" => ["string", "number", "boolean", "array"]], "description" => "Positional arguments for the predicate placeholders, one per placeholder in order."],
                "properties" => ["type" => "array", "items" => ["type" => "string"], "description" => "Attribute names to return, as a JSON array of strings, e.g. [\"objectID\", \"name\", \"sku\"] — never a single bracketed string. For related objects use \"relationships\" or, for nesting, \"serialization\"."],
                "relationships" => ["type" => "object", "description" => "Shallow include, one level deep: {relationshipName: [attribute names]}, e.g. {\"customer\": [\"name\", \"email\"]}. For deeper nesting use \"serialization\"."],
                "serialization" => ["type" => "object", "description" => "Projection for traversing relationships to ANY depth. Recursive object: attribute name → true to include it, relationship name → a nested shape object. objectID and entity name are always included at every level. E.g. {\"orderNumber\": true, \"total\": true, \"customer\": {\"name\": true, \"area\": {\"name\": true}}}. Overrides \"properties\" and \"relationships\" when present."],
                "sort" => ["type" => "array", "items" => ["type" => "object", "properties" => ["key" => ["type" => "string"], "ascending" => ["type" => "boolean"]], "required" => ["key"]], "description" => "Sort descriptors, e.g. [{\"key\": \"creationDate\", \"ascending\": false}]."],
                "limit" => ["type" => "integer", "description" => "Maximum rows to return. Omit to return all matching rows."],
                "offset" => ["type" => "integer"],
            ],
            "required" => ["entity"],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity is required");
        $this->enforceEntityAuthorization($entity, AuthorizationType::read);
        $this->validateProjection($entity, $arguments);
        $this->validateSort($entity, $arguments["sort"]);
        $request = $this->fetchRequest($entity);
        $this->applyPredicate($request, $entity, $arguments);
        $this->applySecurityScope($request);
        $this->applySort($request, $arguments["sort"]);
        $request->fetchLimit = (int)$arguments["limit"];
        $request->fetchOffset = (int)$arguments["offset"];
        $shape = $this->resolveShape($arguments);
        if ($shape !== null) {
            $request->serialization = $shape;
        }
        $results = $this->context->fetch($request);
        $serialized = $this->serializeResults($results, $shape);
        return $this->jsonResult(["rowCount" => $results->count, "results" => $serialized, "summary" => $this->buildSummary($entity, $arguments, $results->count)]);
    }

    /**
     * Restates the call and its outcome for the model, in the words it used to make the call.
     *
     * It is placed last in the result, after the rows, deliberately: a model attends most to the beginning and the end of what it is given, and the summary is where the "this is the whole answer, do not run the same query again" instruction lives. Ahead of the rows it lands in the middle — the one position that gets read least, and precisely where a long result set buries it.
     *
     * @param string $entityName The entity the fetch ran against.
     * @param Dictionary<mixed> $arguments The arguments the call was made with.
     * @param int $rowCount How many rows came back.
     */
    private function buildSummary(string $entityName, Dictionary $arguments, int $rowCount): string
    {
        $parts = new ArrayClass(["Fetched $entityName"]);
        if ($predicate = $arguments["predicate"]) {
            $parts->append("filter: $predicate");
        }
        /** @var ArrayClass<Dictionary<mixed>>|null $sort */
        $sort = $arguments["sort"];
        if ($sort instanceof ArrayClass && !$sort->isEmpty) {
            $sortStr = $sort->map(fn(Dictionary $s): string => ($s["key"] ?? "") . " " . (($s["ascending"] ?? true) ? "ASC" : "DESC"))->join(", ");
            $parts->append("sort: $sortStr");
        }
        $parts->append("$rowCount row(s) returned — result is final, do not retry");
        return $parts->join(". ");
    }

    private function applyPredicate(mixed $request, string $entity, Dictionary $arguments): void
    {
        $predicate = $arguments["predicate"];
        if (!$predicate) {
            return;
        }
        $params = $this->resolveVariables($arguments["arguments"] ?? new ArrayClass());
        $this->validatePredicateKeyPaths($entity, $predicate, $params);
        $request->predicate = $this->buildPredicate($predicate, $params);
    }

    private function validateProjection(string $entity, Dictionary $arguments): void
    {
        /** @var Dictionary<mixed>|null $serialization */
        $serialization = $arguments["serialization"];
        if ($serialization instanceof Dictionary && !$serialization->isEmpty) {
            $this->validateShape($entity, $serialization);
            return;
        }
        foreach ($arguments["properties"] ?? [] as $key) {
            $this->validateKeyPath($entity, (string)$key);
        }
        /** @var Dictionary<Dictionary<mixed>> $relationships */
        $relationships = $arguments["relationships"] ?? new Dictionary();
        if ($relationships->isEmpty) {
            return;
        }
        foreach ($relationships as $name => $props) {
            $relation = $this->entity($entity)->relationships[$name] ?? fatal_error("Unknown relationship \"$name\"");
            foreach ($props as $key) {
                $this->validateKeyPath($relation->target, (string)$key);
            }
        }
    }

    /**
     * Validates a nested serialization shape against the schema: every key must be an attribute or
     * a relationship of the current entity; nested objects are only valid on relationships and are
     * validated recursively against the relationship target.
     *
     * @param string $entity
     * @param Dictionary<mixed> $shape
     */
    private function validateShape(string $entity, Dictionary $shape): void
    {
        $schema = $this->entity($entity);
        foreach ($shape as $key => $value) {
            if ($value instanceof Dictionary) {
                /** @var RelationshipSchema $relationship */
                $relationship = $schema->relationships[$key] ?? fatal_error("\"$key\" is not a relationship on $schema->name. Relationships: {$schema->relationships->keys}.");
                $this->validateShape($relationship->target, $value);
                continue;
            }
            if (!$schema->attributes[$key] && !$schema->relationships[$key]) {
                fatal_error("Unknown property \"$key\" on $schema->name. Attributes: {$schema->attributes->keys}. Relationships: {$schema->relationships->keys}.");
            }
        }
    }

    /**
     * Resolves the serialization shape for this request. Prefers an explicit "serialization" shape;
     * otherwise builds one from the legacy "properties" / "relationships" arguments. Returns null
     * when no projection was requested, so the full default representation is serialized.
     *
     * @return Dictionary<mixed>|null
     */
    private function resolveShape(Dictionary $arguments): ?Dictionary
    {
        /** @var Dictionary<mixed>|null $serialization */
        $serialization = $arguments["serialization"];
        if ($serialization instanceof Dictionary && !$serialization->isEmpty) {
            return $this->normalizeShape($serialization);
        }
        $properties = $arguments["properties"];
        $relationships = $arguments["relationships"];
        if ($properties === null && $relationships === null) {
            return null;
        }
        return $this->buildShape($properties ?? new ArrayClass(), $relationships ?? new Dictionary());
    }

    /**
     * Normalizes an incoming shape so attribute leaves are stored as `true` and relationship
     * subtrees stay nested dictionaries, matching what FetchRequest->serialization expects.
     *
     * @param Dictionary<mixed> $shape
     * @return Dictionary<mixed>
     */
    private function normalizeShape(Dictionary $shape): Dictionary
    {
        /** @var Dictionary<mixed> $normalized */
        $normalized = new Dictionary();
        foreach ($shape as $key => $value) {
            $normalized[$key] = $value instanceof Dictionary ? $this->normalizeShape($value) : true;
        }
        return $normalized;
    }

    private function validateSort(string $entity, ?ArrayClass $sort): void
    {
        foreach (($sort ?? []) as $item) {
            $key = (string)$item["key"];
            if ($key === "") {
                continue;
            }
            $this->validateKeyPath($entity, $key);
        }
    }

    /**
     * @param FetchRequest $request
     * @param ArrayClass<Dictionary<mixed>>|null $sort
     */
    private function applySort(FetchRequest $request, ?ArrayClass $sort): void
    {
        if (!$sort) {
            return;
        }
        $request->sortDescriptors = $sort->compactMap(fn(Dictionary $item): ?SortDescriptor => ($key = $item["key"]) ? new SortDescriptor($key, (bool)($item["ascending"] ?? true)) : null);
    }

    /**
     * Serializes each fetched object, filtering the result through the field security policy when
     * security is enabled. Filtering applies to top-level fields only — the same behavior as the
     * PersistentSpace read strategy; nested relationship leaves in a serialization shape are not
     * filtered.
     *
     * @param ArrayClass<ManagedObject> $results
     * @param Dictionary<mixed>|null $shape
     * @throws Exception
     */
    private function serializeResults(ArrayClass $results, ?Dictionary $shape): array
    {
        $serialize = fn(ManagedObject $object): Dictionary => $shape === null ? $object->jsonSerialize() : $object->serialized($shape)->jsonSerialize();
        if (!$this->isSecurityEnabled) {
            return $results->map($serialize)->array;
        }
        return $results->map(fn(ManagedObject $object): Dictionary => $this->applySecureRead($object, $serialize($object)))->array;
    }

    private function buildShape(ArrayClass $properties, Dictionary $relationships): Dictionary
    {
        /** @var Dictionary<mixed> $shape */
        $shape = new Dictionary();
        foreach ($properties as $property) {
            $shape[$property] = true;
        }
        foreach ($relationships as $name => $props) {
            /** @var Dictionary<bool> $subShape */
            $subShape = new Dictionary();
            foreach ($props as $property) {
                $subShape[$property] = true;
            }
            $shape[$name] = $subShape;
        }
        return $shape;
    }
}
