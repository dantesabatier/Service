<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\NotFoundException;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

final class UpdateTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "update";
    }
    #[Override]
    public string $description {
        get => "Update an existing entity.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string"], "objectID" => ["type" => "integer"], "values" => ["type" => "object"]],
            "required" => ["entity", "objectID", "values"],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity is required");
        $objectID = $arguments["objectID"] ?? fatal_error("objectID is required");
        $values = $arguments["values"] ?? fatal_error("values is required");
        $request = $this->fetchRequest($entity);
        $request->predicate = $this->buildPredicate("%K = %d", new ArrayClass([ManagedObjectObjectIDKey, $objectID]));
        $object = $this->context->fetch($request)->first ?? throw new NotFoundException();
        $object->updateFromSnapshot($values);
        if ($this->context->hasChanges) {
            $this->context->save();
        }
        return $this->jsonResult($object->jsonSerialize());
    }
}
