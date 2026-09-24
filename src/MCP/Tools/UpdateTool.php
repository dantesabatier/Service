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

/** @internal */
final class UpdateTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "update";
    }
    #[Override]
    public bool $isOpenWorld {
        get => false;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."], "objectID" => ["type" => "integer", "description" => "Always required. The row to update."], "values" => ["type" => "object", "description" => "Only the fields to change — anything you omit is left as it is, so a single attribute is a valid update: {\"status\": 2} touches one column. Relationships take the same shape as in create and write the whole graph in this one call: a nested object with no objectID is created, one carrying an objectID is the row that already exists and any fields alongside it update that row. So {\"customer\": 42} relinks, {\"customer\": {\"objectID\": 42, \"email\": \"a@b.c\"}} also updates that customer rather than adding a second one, and {\"items\": [{\"sku\": \"X\"}]} adds an item to the to-many. When the relationship targets an entity the schema marks `\"abstract\": true`, every nested object must name the concrete sub-entity with \"entityName\", since the abstract one cannot be instantiated. Sending a to-many REPLACES its contents: a row you leave out is not merely unlinked but deleted if the relationship cascades, so send every row you mean to keep, each carrying its objectID. To touch one child, fetch the relationship first and send it back whole. Only fields you are authorized to write are applied; the rest are discarded."]],
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
        $this->applySecurityScope($request);
        $object = $this->context->fetch($request)->first ?? throw new NotFoundException();
        $this->enforceOwnership($object);
        $this->enforceResourceAccess($object);
        $this->applySecureUpdate($object, $this->normalizeRelationships($entity, $values));
        if ($this->context->hasChanges) {
            $this->context->save();
        }
        return $this->jsonResult($this->applySecureRead($object, $object->jsonSerialize()));
    }
}
