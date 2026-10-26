<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\DeletePersistentSpaceOperation;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class DeleteTool extends WriteTool
{
    #[Override]
    public string $name {
        get => "delete";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entityName" => self::entityNameArgument, "objectID" => ["type" => "integer", "description" => "Always required. The row to delete; the rows related to it follow each relationship's deleteRule."]],
            "required" => ["entityName", "objectID"],
        ];
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
        $entityName = $this->entityName($arguments);
        $objectID = $arguments["objectID"] ?? fatal_error("objectID is required");
        new DeletePersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->entity($entityName), $objectID)->perform();
        return $this->jsonResult(["deleted" => $objectID]);
    }
}
