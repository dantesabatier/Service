<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use InvalidArgumentException;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class FetchTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "fetch";
    }
    #[Override]
    public string $description {
        get => "Fetch entity rows with optional filtering, sorting and pagination. Call once per query — trust the result even if count is 0; do not retry with rephrased predicates.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."],
                "predicate" => ["type" => "string", "description" => "NSPredicate format string, e.g. \"%K == %@\". Use %K for key paths, %@ for strings/objects, %d for integers, %f for floats."],
                "arguments" => ["type" => "array", "items" => ["type" => ["string", "number", "boolean", "array"]], "description" => "Positional arguments for the predicate placeholders, one per placeholder in order."],
                "properties" => ["type" => "array", "items" => ["type" => "string"], "description" => "Attribute names to return. Must be a JSON array of strings, e.g. [\"objectID\", \"name\", \"sku\"]. Never pass a single bracketed string."],
                "relationships" => ["type" => "object", "description" => "Relationships to include. Keys are relationship names; values are arrays of attribute names to return from the related object, e.g. {\"customer\": [\"name\", \"email\"]}."],
                "sort" => ["type" => "array", "items" => ["type" => "object", "properties" => ["key" => ["type" => "string"], "ascending" => ["type" => "boolean"]], "required" => ["key"]], "description" => "Sort descriptors, e.g. [{\"key\": \"creationDate\", \"ascending\": false}]."],
                "limit" => ["type" => "integer"],
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
        $this->validateProjection($entity, $arguments);
        $this->validateSort($entity, $arguments["sort"]);
        $request = $this->fetchRequest($entity);
        $this->applyPredicate($request, $entity, $arguments);
        $this->applySort($request, $arguments["sort"]);
        $request->fetchLimit = (int)($arguments["limit"] ?? 100);
        $request->fetchOffset = (int)($arguments["offset"] ?? 0);
        $results = $this->context->fetch($request);
        $serialized = $this->serializeResults($results, $arguments["properties"], $arguments["relationships"]);
        return $this->jsonResult(["rowCount" => $results->count, "summary" => $this->buildSummary($entity, $arguments, $results->count), "results" => $serialized]);
    }

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
        foreach ($arguments["properties"] ?? [] as $key) {
            $this->validateKeyPath($entity, (string)$key);
        }
        /** @var Dictionary<Dictionary<mixed>> $relationships */
        $relationships = $arguments["relationships"] ?? new Dictionary();
        if ($relationships->isEmpty) {
            return;
        }
        foreach ($relationships as $name => $props) {
            $relation = $this->entity($entity)->relationships[$name] ?? throw new InvalidArgumentException("Unknown relationship \"$name\"");
            foreach ($props as $key) {
                $this->validateKeyPath($relation->target, (string)$key);
            }
        }
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

    private function serializeResults(ArrayClass $results, mixed $properties, mixed $relationships): array
    {
        if ($properties === null && $relationships === null) {
            return $results->map(fn(ManagedObject $object) => $object->jsonSerialize())->array;
        }
        $shape = $this->buildShape($properties ?? new ArrayClass(), $relationships ?? new Dictionary());
        return $results->map(fn(ManagedObject $object) => $object->serialized($shape)->jsonSerialize())->array;
    }

    private function buildShape(ArrayClass $properties, Dictionary $relationships): Dictionary
    {
        /** @var Dictionary<bool> $shape */
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
