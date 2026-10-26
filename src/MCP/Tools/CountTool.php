<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\CountPersistentSpaceOperation;
use Sabatier\Service\MCP\Response\ContentItem;

/** @internal */
final class CountTool extends FetchTool
{
    #[Override]
    public string $name {
        get => "count";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object", "properties" => ["entityName" => self::requestSchema["entityName"], "predicate" => self::requestSchema["predicate"], "includesSubentities" => self::requestSchema["includesSubentities"]], "required" => ["entityName"]];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult(["entityName" => $arguments["entityName"], "count" => new CountPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->fetchRequest($arguments))->perform()]);
    }
}
