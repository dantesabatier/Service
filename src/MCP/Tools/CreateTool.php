<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\CreatePersistentSpaceOperation;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class CreateTool extends WriteTool
{
    #[Override]
    public string $name {
        get => "create";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entityName" => self::entityNameArgument, "values" => ["type" => "object", "description" => "Attribute name → value, and relationship name → the related object, nested to any depth: the whole graph is written in this one call. A nested object with no objectID is created; one carrying an objectID is the row that already exists, and any other fields alongside it update that row instead of duplicating it. Either form works for a to-one relationship or inside the array of a to-many, and a bare integer is shorthand for linking by objectID. E.g. {\"orderNumber\": \"A-1\", \"customer\": 42, \"items\": [{\"sku\": \"X\", \"quantity\": 2}, {\"objectID\": 7, \"quantity\": 5}]} links an existing customer, creates one item and updates another. When the relationship targets an entity the schema marks `\"abstract\": true`, every nested object must name the concrete sub-entity to create with \"entityName\", since the abstract one cannot be instantiated. Only fields you are authorized to write are applied; the rest are discarded."]],
            "required" => ["entityName", "values"],
        ];
    }

    #[Override]
    public function authorizationAction(Dictionary $arguments): AuthorizationType
    {
        return AuthorizationType::create;
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        $entityName = $this->entityName($arguments);
        /** @var Dictionary<mixed> $values */
        $values = $arguments["values"] ?? fatal_error("values is required");
        $entity = $this->entity($entityName);
        $this->assertConcreteEntity($entity);
        return $this->jsonResult(new CreatePersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $entity, $this->normalizeRelationships($entityName, $values), $this->shapeFromValues($entityName, $values))->perform());
    }
}
