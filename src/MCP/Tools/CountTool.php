<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\CountPersistentSpaceOperation;
use Sabatier\Service\MCP\Response\ContentItem;
use const Sabatier\CoreData\ManagedObjectEntityNameKey;
use const Sabatier\Service\MCPPredicateArgumentKey;

/** @internal */
final class CountTool extends FetchTool
{
    #[Override]
    protected(set) string $name = "count";
    #[Override]
    protected(set) array $inputSchema = ["type" => "object", "properties" => [ManagedObjectEntityNameKey => self::requestSchema[ManagedObjectEntityNameKey], MCPPredicateArgumentKey => self::requestSchema[MCPPredicateArgumentKey], "includesSubentities" => self::requestSchema["includesSubentities"]], "required" => [ManagedObjectEntityNameKey]];

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(): ArrayClass
    {
        return $this->jsonResult([ManagedObjectEntityNameKey => $this->arguments[ManagedObjectEntityNameKey], "count" => new CountPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->fetchRequest)->perform()]);
    }
}
