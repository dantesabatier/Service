<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\ToolCallToFetchRequestAdapter;
use Sabatier\Service\ReadPersistentSpaceOperation;

/**
 * @internal
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
class FetchTool extends AbstractTool
{
    /** @var array<array-key, mixed> The arguments every query tool accepts, named after the fetch request properties they set. */
    protected const array requestSchema = [
        "entityName" => ["type" => "string", "description" => "Required. Entity name from the data model — call describe_model first if unsure."],
        "predicate" => ["type" => "object", "description" => "Filters the rows: {\"format\": \"%K == %@\", \"arguments\": [...]}. %K for key paths, %@ for strings/objects, %d for integers, %f for floats, one argument per placeholder in order."],
        "serialization" => ["type" => "object", "description" => "The shape of each row, nested to any depth: {\"name\": true, \"customer\": {\"name\": true}}. Omit to return every attribute."],
        "sortDescriptors" => ["type" => "array", "description" => "Sort order: [{\"key\": \"creationDate\", \"ascending\": false}]."],
        "fetchLimit" => ["type" => "integer", "description" => "Maximum rows to return. Omit for all of them."],
        "fetchOffset" => ["type" => "integer"],
        "fetchBatchSize" => ["type" => "integer"],
        "includesSubentities" => ["type" => "boolean", "description" => "Whether a fetch on an abstract entity includes the rows of its concrete sub-entities. True by default."],
        "returnsDistinctResults" => ["type" => "boolean"],
    ];

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
        get => ["type" => "object", "properties" => self::requestSchema, "required" => ["entityName"]];
    }

    /**
     * The resource a query tool is authorized against is the entity it reads.
     *
     * @param Dictionary<mixed> $arguments The arguments selecting the concrete operation.
     */
    #[Override]
    public function authorizationResource(Dictionary $arguments): ?string
    {
        return is_string($entityName = $arguments["entityName"]) ? $entityName : null;
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        $rows = new ReadPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->fetchRequest($arguments))->perform();
        return $this->jsonResult(["rowCount" => $rows->count, "results" => $rows, "summary" => $this->summary($arguments, $rows->count)]);
    }

    /**
     * The request this call reads through, built from the arguments the way `ReadPersistentSpaceResponseStrategy` builds one from an HTTP request.
     *
     * @param Dictionary<mixed> $arguments
     * @throws Exception
     */
    protected function fetchRequest(Dictionary $arguments): FetchRequest
    {
        return new ToolCallToFetchRequestAdapter($arguments, $this->context)->fetchRequest;
    }

    /**
     * Restates the call and its outcome for the model, in the words it used to make the call.
     *
     * It is placed last in the result, after the rows, deliberately: a model attends most to the beginning and the end of what it is given, and the summary is where the "this is the whole answer, do not run the same query again" instruction lives. Ahead of the rows it lands in the middle — the one position that gets read least, and precisely where a long result set buries it.
     *
     * @param Dictionary<mixed> $arguments The arguments the call was made with.
     * @param int $rowCount How many rows came back.
     */
    protected function summary(Dictionary $arguments, int $rowCount): string
    {
        $parts = new ArrayClass(["Fetched {$arguments["entityName"]}"]);
        if (($predicate = $arguments["predicate"]) instanceof Dictionary) {
            $parts->append("filter: {$predicate["format"]}");
        }
        $parts->append("$rowCount row(s) returned — result is final, do not retry");
        return $parts->join(". ");
    }
}
