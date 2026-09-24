<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class GroupByTool extends AbstractTool
{
    /** @var list<string> */
    private const array allowedFunctions = ["sum", "average", "min", "max", "count"];
    /** @var array<string, string> */
    private const array expressionNames = ["sum" => "sum:", "average" => "average:", "min" => "min:", "max" => "max:", "count" => "count:"];
    /** @var array<string, AttributeType> */
    private const array resultTypes = ["sum" => AttributeType::double, "average" => AttributeType::double, "min" => AttributeType::double, "max" => AttributeType::double, "count" => AttributeType::integer64];

    #[Override]
    public string $name {
        get => "group_by";
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
                "entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."],
                "group_by" => ["type" => "array", "description" => "Property key paths to group by. Only plain property names or dot-notation key paths (e.g. \"status\", \"customer.name\"). No transforms or expressions."],
                "aggregates" => ["type" => "array", "description" => "Aggregate functions to compute per group. Each item: {\"function\": \"sum|average|min|max|count\", \"property\": \"keyPath\", \"as\": \"resultName\"}. E.g. [{\"function\": \"sum\", \"property\": \"total\", \"as\": \"revenue\"}, {\"function\": \"count\", \"property\": \"objectID\", \"as\": \"orders\"}]."],
                "predicate" => ["type" => "string", "description" => "Filters the rows BEFORE grouping, in NSPredicate format. E.g. \"%K >= %@\"."],
                "arguments" => ["type" => "array", "description" => "Positional arguments for the predicate placeholders, one per placeholder in order."],
                "having_predicate" => ["type" => "string", "description" => "Filters the groups AFTER aggregating, by the names given in \"as\". E.g. \"%K > %d\" with having_arguments [\"revenue\", 1000] keeps only groups whose summed total exceeds 1000. Filter raw rows with \"predicate\" instead — a key path that is not an aggregate result does not exist at this stage."],
                "having_arguments" => ["type" => "array", "description" => "Positional arguments for the having_predicate placeholders, one per placeholder in order."],
                "sort" => ["type" => "array", "description" => "Sort descriptors over the grouped rows, by a group_by key path or an aggregate name. E.g. [{\"key\": \"revenue\", \"ascending\": false}]."],
                "limit" => ["type" => "integer", "description" => "Maximum rows to return. Omit to return all matching rows."],
                "offset" => ["type" => "integer"],
            ],
            "required" => ["entity", "group_by", "aggregates"],
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
        $groupBy = $this->groupKeys($entity, $arguments["group_by"] ?? fatal_error("group_by is required"));
        $request = $this->fetchRequest($entity);
        $aggregates = $this->aggregateDescriptions($request, $entity, $arguments["aggregates"] ?? fatal_error("aggregates is required"));
        $request->propertiesToFetch = new ArrayClass([...$groupBy, ...$aggregates]);
        $request->propertiesToGroupBy = $groupBy;
        $request->resultType = FetchRequestResultType::dictionaryResultType;
        if ($predicate = $arguments["predicate"]) {
            $params = $this->resolveVariables($arguments["arguments"] ?? new ArrayClass());
            $this->validatePredicateKeyPaths($entity, $predicate, $params);
            $request->predicate = $this->buildPredicate($predicate, $params);
        }
        $this->applySecurityScope($request);
        if ($having = $arguments["having_predicate"]) {
            $request->havingPredicate = $this->buildPredicate($having, $this->resolveVariables($arguments["having_arguments"] ?? new ArrayClass()));
        }
        /** @var ArrayClass<Dictionary<mixed>>|null $sort */
        $sort = $arguments["sort"];
        if ($sort instanceof ArrayClass) {
            $request->sortDescriptors = $sort->compactMap(fn(Dictionary $item): ?SortDescriptor => ($key = $item["key"]) ? new SortDescriptor($key, (bool)($item["ascending"] ?? true)) : null);
        }
        $request->fetchLimit = (int)$arguments["limit"];
        $request->fetchOffset = (int)$arguments["offset"];
        /** @var ArrayClass<Dictionary> $rows */
        $rows = $this->context->fetch($request);
        $groupByPaths = $arguments["group_by"];
        $normalized = $rows->map(fn(Dictionary $row): Dictionary => $this->normalizeRow($row, $groupByPaths));
        return $this->jsonResult(["rowCount" => $rows->count, "results" => $normalized, "summary" => $this->buildSummary($entity, $arguments, $rows->count)]);
    }

    private function buildSummary(string $entityName, Dictionary $arguments, int $rowCount): string
    {
        /** @var ArrayClass<string> $groupByPaths */
        $groupByPaths = $arguments["group_by"];
        /** @var ArrayClass<Dictionary<mixed>> $aggregates */
        $aggregates = $arguments["aggregates"];
        $aggregateStr = $aggregates->map(fn(Dictionary $a): string => "{$a['function']}({$a['property']}) as {$a['as']}")->join(", ");
        $parts = new ArrayClass(["Grouped $entityName by [{$groupByPaths->join(', ')}] computing [$aggregateStr]"]);
        if ($predicate = $arguments["predicate"]) {
            $parts->append("filter: $predicate");
        }
        $parts->append("$rowCount row(s) returned — result is final, do not retry");
        return $parts->join(". ");
    }

    private function normalizeRow(Dictionary $row, ArrayClass $groupByPaths): Dictionary
    {
        // Exclude nested root segments (e.g. "seller") and literal dot-path keys (e.g. "seller.name") that CoreData may include, then re-add each path using its leaf segment as the key.
        $pathSet = new Set($groupByPaths);
        $rootSet = new Set($groupByPaths->map(fn(string $path): string => /** @var string */ new ArrayClass(explode(".", $path))->first ?? $path));
        $result = $row->filter(fn(mixed $value, string $key): bool => !$rootSet->containsElement($key) && !$pathSet->containsElement($key));
        foreach ($groupByPaths as $path) {
            $parts = new ArrayClass(explode(".", (string)$path));
            // CoreData may return nested objects, a literal dot-key, or already-flat keys.
            $value = $this->resolveNestedValue($row, $parts) ?? $row[$path];
            if ($value !== null) {
                $result[(string)$parts->last] = $value;
            }
        }
        return $result;
    }

    private function resolveNestedValue(Dictionary $data, ArrayClass $parts): mixed
    {
        $current = $data;
        foreach ($parts as $part) {
            if (!($current instanceof Dictionary)) {
                return null;
            }
            $current = $current[$part] ?? $current[strtolower((string)$part)];
        }
        return $current;
    }

    /**
     * @return ArrayClass<string>
     * @throws Exception
     */
    private function groupKeys(string $entity, iterable $keys): ArrayClass
    {
        /** @var ArrayClass<string> $result */
        $result = new ArrayClass();
        foreach ($keys as $key) {
            $this->validateKeyPath($entity, (string)$key);
            $this->enforceFieldRead($entity, (string)$key);
            $result->append((string)$key);
        }
        return $result;
    }

    /**
     * @return ArrayClass<ExpressionDescription>
     * @throws Exception
     */
    private function aggregateDescriptions(mixed $request, string $entity, iterable $items): ArrayClass
    {
        /** @var ArrayClass<ExpressionDescription> $result */
        $result = new ArrayClass();
        foreach ($items as $item) {
            $function = (string)($item["function"] ?? fatal_error("aggregate.function required"));
            $property = (string)($item["property"] ?? fatal_error("aggregate.property required"));
            in_array($function, self::allowedFunctions, true) ?: fatal_error("Invalid aggregate function");
            $this->validateKeyPath($entity, $property);
            $this->enforceFieldRead($entity, $property);
            $description = new ExpressionDescription();
            $description->entity = $request->entity ?? fatal_error("Missing entity");
            $description->name = (string)($item["as"] ?? "{$function}_$property");
            $description->expression = Expression::expressionWithFormat(self::expressionNames[$function] . "(%K)", new ArrayClass([$property]));
            $description->resultType = self::resultTypes[$function];
            $result->append($description);
        }
        return $result;
    }
}
