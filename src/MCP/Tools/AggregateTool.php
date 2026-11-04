<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\ReadPersistentSpaceOperation;
use const Sabatier\CoreData\ManagedObjectEntityNameKey;

/** @internal */
final class AggregateTool extends GroupByTool
{
    #[Override]
    protected(set) string $name = "aggregate";
    #[Override]
    protected(set) array $inputSchema = ["type" => "object", "properties" => [...self::requestSchema, "propertiesToFetch" => self::computationSchema["propertiesToFetch"]], "required" => [ManagedObjectEntityNameKey, "propertiesToFetch"]];

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(): ArrayClass
    {
        $rows = new ReadPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->fetchRequest)->perform();
        return $this->jsonResult([ManagedObjectEntityNameKey => $this->arguments[ManagedObjectEntityNameKey], "results" => $rows->first, "summary" => $this->summary($rows->count)]);
    }

    /**
     * @param int $rowCount How many rows came back.
     */
    #[Override]
    protected function summary(int $rowCount): string
    {
        return "Aggregated {$this->arguments[ManagedObjectEntityNameKey]} — result is final, do not retry";
    }
}
