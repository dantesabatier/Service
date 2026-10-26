<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;

/** @internal */
final class PurgeHistoryTool extends PersistentHistoryTool
{
    #[Override]
    public string $name {
        get => "purge_history";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object", "properties" => [...self::purgeScopeSchema, ...self::transactionFilterSchema]];
    }

    #[Override]
    public function authorizationAction(Dictionary $arguments): AuthorizationType
    {
        return AuthorizationType::delete;
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        $this->perform($arguments);
        return $this->jsonResult(["purged" => true]);
    }
}
