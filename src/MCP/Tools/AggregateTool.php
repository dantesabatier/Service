<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\ReadPersistentSpaceOperation;

/** @internal */
final class AggregateTool extends GroupByTool
{
    #[Override]
    public string $name {
        get => "aggregate";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object", "properties" => [...self::requestSchema, "propertiesToFetch" => self::computationSchema["propertiesToFetch"]], "required" => ["entityName", "propertiesToFetch"]];
    }

    /**
     * An aggregate computes over the whole result rather than over groups of it, so nothing narrows the rows into groups and the result type has to be asked for.
     *
     * @param Dictionary<mixed> $arguments
     * @throws Exception
     */
    #[Override]
    protected function fetchRequest(Dictionary $arguments): FetchRequest
    {
        $request = parent::fetchRequest($arguments);
        $request->resultType = FetchRequestResultType::dictionaryResultType;
        return $request;
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        $rows = new ReadPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->fetchRequest($arguments))->perform();
        return $this->jsonResult(["entityName" => $arguments["entityName"], "results" => $rows->first, "summary" => $this->summary($arguments, $rows->count)]);
    }

    /**
     * @param Dictionary<mixed> $arguments The arguments the call was made with.
     * @param int $rowCount How many rows came back.
     */
    #[Override]
    protected function summary(Dictionary $arguments, int $rowCount): string
    {
        return "Aggregated {$arguments["entityName"]} — result is final, do not retry";
    }
}
