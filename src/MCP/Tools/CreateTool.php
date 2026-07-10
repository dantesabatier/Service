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
            "properties" => ["entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."], "values" => ["type" => "object"]],
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
        $this->context->save();
        return $this->jsonResult($this->applySecureRead($object, $object->serialized($this->shapeFromValues($entity, $values))->jsonSerialize()));
    }
}
