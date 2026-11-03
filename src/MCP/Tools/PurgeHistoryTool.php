<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;

/** @internal */
final class PurgeHistoryTool extends PersistentHistoryTool
{
    #[Override]
    protected(set) string $name = "purge_history";
    #[Override]
    protected(set) array $inputSchema = ["type" => "object", "properties" => [...self::purgeScopeSchema, ...self::transactionFilterSchema]];
    #[Override]
    public AuthorizationType $authorizationAction {
        get => AuthorizationType::delete;
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(): ArrayClass
    {
        $this->perform();
        return $this->jsonResult(["purged" => true]);
    }
}
