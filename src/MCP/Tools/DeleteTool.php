<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class DeleteTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "delete";
    }
    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."], "objectID" => ["type" => "integer"]],
            "required" => ["entity", "objectID"],
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
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity is required");
        $objectID = $arguments["objectID"] ?? fatal_error("objectID is required");
        $this->delete($entity, $objectID);
        return $this->jsonResult(["deleted" => $objectID]);
    }
}
