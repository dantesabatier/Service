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
        get => "Fetch entities with filtering, sorting and pagination.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."],
                "predicate" => ["type" => "string"],
                "arguments" => ["type" => "array"],
                "properties" => ["type" => "array"],
                "relationships" => ["type" => "object"],
                "sort" => ["type" => "array"],
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
        return $this->jsonResult(["count" => $results->count, "results" => $this->serializeResults($results, $arguments["properties"], $arguments["relationships"])]);
    }

    private function applyPredicate(mixed $request, string $entity, Dictionary $arguments): void
    {
        $predicate = $arguments["predicate"];
        $params = $arguments["arguments"] ?? new ArrayClass();
        if (!$predicate) {
            return;
        }
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
