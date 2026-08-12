<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class CreateTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "create";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."], "values" => ["type" => "object", "description" => "Attribute name → value, and relationship name → the related object, nested to any depth: the whole graph is written in this one call. A nested object with no objectID is created; one carrying an objectID is the row that already exists, and any other fields alongside it update that row instead of duplicating it. Either form works for a to-one relationship or inside the array of a to-many, and a bare integer is shorthand for linking by objectID. E.g. {\"orderNumber\": \"A-1\", \"customer\": 42, \"items\": [{\"sku\": \"X\", \"quantity\": 2}, {\"objectID\": 7, \"quantity\": 5}]} links an existing customer, creates one item and updates another. When the relationship targets an entity the schema marks `\"abstract\": true`, every nested object must name the concrete sub-entity to create with \"entityName\", since the abstract one cannot be instantiated. Only fields you are authorized to write are applied; the rest are discarded."]],
            "required" => ["entity", "values"],
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
        /** @var Dictionary<mixed> $values */
        $values = $arguments["values"] ?? fatal_error("values is required");
        $this->enforceEntityAuthorization($entity, AuthorizationType::create);
        $this->assertConcreteEntity($entity);
        $object = EntityDescription::insertNewObject($entity, $this->context);
        $this->applySecureUpdate($object, $this->normalizeRelationships($entity, $values));
        $this->enforceResourceAccess($object);
        $this->context->save();
        return $this->jsonResult($this->applySecureRead($object, $object->serialized($this->shapeFromValues($entity, $values))->jsonSerialize()));
    }
}
