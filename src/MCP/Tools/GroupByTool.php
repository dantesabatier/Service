<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;

/** @internal */
class GroupByTool extends FetchTool
{
    /** @var array<array-key, mixed> The arguments a grouped or computed query adds to the ordinary ones. */
    protected const array computationSchema = [
        "propertiesToFetch" => ["type" => "array", "description" => "What each row holds: an attribute name, or a computed property {\"name\": \"revenue\", \"expression\": {\"format\": \"sum:(%K)\", \"arguments\": [\"total\"]}, \"resultType\": 2}. Functions are Core Data expression functions — sum:, average:, min:, max:, count:, median:, mode:, stddev:. resultType is the attribute type of the result: 2 integer, 4 double."],
        "propertiesToGroupBy" => ["type" => "array", "description" => "Attribute names to group by, e.g. [\"creationDate\"]. Every grouped key path must also appear in propertiesToFetch."],
        "havingPredicate" => ["type" => "object", "description" => "Filters the groups after aggregating, by the names given in propertiesToFetch: {\"format\": \"%K > %d\", \"arguments\": [\"revenue\", 1000]}. Filter raw rows with predicate instead."],
    ];

    #[Override]
    protected(set) string $name = "group_by";
    #[Override]
    protected(set) array $inputSchema = ["type" => "object", "properties" => [...self::requestSchema, ...self::computationSchema], "required" => ["entityName", "propertiesToFetch", "propertiesToGroupBy"]];
    /** @var FetchRequest A grouped or computed query answers dictionaries, one per group, rather than objects. */
    #[Override]
    protected FetchRequest $fetchRequest {
        get {
            $request = parent::$fetchRequest::get();
            $request->resultType = FetchRequestResultType::dictionaryResultType;
            return $request;
        }
    }

    /**
     * @param int $rowCount How many rows came back.
     */
    #[Override]
    protected function summary(int $rowCount): string
    {
        return "Grouped {$this->arguments["entityName"]}. $rowCount group(s) returned — result is final, do not retry";
    }
}
