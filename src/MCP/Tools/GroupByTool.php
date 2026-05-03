<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use InvalidArgumentException;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

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
    public string $description {
        get => "Group rows and compute aggregates.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string"],
                "group_by" => ["type" => "array"],
                "aggregates" => ["type" => "array"],
                "predicate" => ["type" => "string"],
                "arguments" => ["type" => "array"],
                "having_predicate" => ["type" => "string"],
                "having_arguments" => ["type" => "array"],
                "sort" => ["type" => "array"],
                "limit" => ["type" => "integer"],
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
        $entity = $arguments["entity"] ?? throw new InvalidArgumentException("entity is required");
        $groupBy = $this->groupKeys($entity, $arguments["group_by"] ?? throw new InvalidArgumentException("group_by is required"));
        $request = $this->fetchRequest($entity);
        $aggregates = $this->aggregateDescriptions($request, $entity, $arguments["aggregates"] ?? throw new InvalidArgumentException("aggregates is required"));
        $request->propertiesToFetch = new ArrayClass([...$groupBy, ...$aggregates]);
        $request->propertiesToGroupBy = $groupBy;
        $request->resultType = FetchRequestResultType::dictionaryResultType;
        if ($predicate = $arguments["predicate"]) {
            $params = $arguments["arguments"] ?? new ArrayClass();
            $this->validatePredicateKeyPaths($entity, $predicate, $params);
            $request->predicate = $this->buildPredicate($predicate, $params);
        }
        if ($having = $arguments["having_predicate"]) {
            $request->havingPredicate = $this->buildPredicate($having, $arguments["having_arguments"] ?? new ArrayClass());
        }
        if ($sort = $arguments["sort"]) {
            $request->sortDescriptors = $sort->map(fn(Dictionary $item) => new SortDescriptor($item["key"], $item["ascending"] ?? true));
        }
        $request->fetchLimit = (int)($arguments["limit"] ?? 100);
        $request->fetchOffset = (int)($arguments["offset"] ?? 0);
        /** @var ArrayClass<Dictionary> $rows */
        $rows = $this->context->fetch($request);
        return $this->jsonResult(["count" => count($rows), "results" => $rows->map(fn(Dictionary $row): array => $row->jsonSerialize())->array]);
    }

    private function groupKeys(string $entity, iterable $keys): ArrayClass
    {
        /** @var ArrayClass<string> $result */
        $result = new ArrayClass();
        foreach ($keys as $key) {
            $this->validateKeyPath($entity, (string)$key);
            $result->append((string)$key);
        }
        return $result;
    }

    private function aggregateDescriptions(mixed $request, string $entity, iterable $items): ArrayClass
    {
        /** @var ArrayClass<ExpressionDescription> $result */
        $result = new ArrayClass();
        foreach ($items as $item) {
            $function = (string)($item["function"] ?? throw new InvalidArgumentException("aggregate.function required"));
            $property = (string)($item["property"] ?? throw new InvalidArgumentException("aggregate.property required"));
            in_array($function, self::allowedFunctions, true) ?: fatal_error("Invalid aggregate function");
            $this->validateKeyPath($entity, $property);
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
