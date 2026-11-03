<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\CountPersistentSpaceOperation;
use Sabatier\Service\MCP\Response\ContentItem;

/** @internal */
final class CountTool extends FetchTool
{
    #[Override]
    protected(set) string $name = "count";
    #[Override]
    protected(set) array $inputSchema = ["type" => "object", "properties" => ["entityName" => self::requestSchema["entityName"], "predicate" => self::requestSchema["predicate"], "includesSubentities" => self::requestSchema["includesSubentities"]], "required" => ["entityName"]];

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(): ArrayClass
    {
        return $this->jsonResult(["entityName" => $this->arguments["entityName"], "count" => new CountPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->fetchRequest)->perform()]);
    }
}
