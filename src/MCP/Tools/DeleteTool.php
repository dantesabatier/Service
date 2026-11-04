<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\DeletePersistentSpaceOperation;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectEntityNameKey;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final class DeleteTool extends WriteTool
{
    #[Override]
    protected(set) string $name = "delete";
    #[Override]
    protected(set) array $inputSchema = [
        "type" => "object",
        "properties" => [ManagedObjectEntityNameKey => self::entityNameArgument, ManagedObjectObjectIDKey => ["type" => "integer", "description" => "Always required. The row to delete; the rows related to it follow each relationship's deleteRule."]],
        "required" => [ManagedObjectEntityNameKey, ManagedObjectObjectIDKey],
    ];
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
        $entityName = $this->entityName;
        /** @var int $objectID */
        $objectID = $this->arguments[ManagedObjectObjectIDKey] ?? fatal_error("objectID is required");
        new DeletePersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $this->entity($entityName), $objectID)->perform();
        return $this->jsonResult(["deleted" => $objectID]);
    }
}
