<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
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
    public string $description {
        get => "Create a new entity row.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string"], "values" => ["type" => "object"]],
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
        $values = $arguments["values"] ?? fatal_error("values is required");
        $object = EntityDescription::insertNewObject($entity, $this->context);
        $object->updateFromSnapshot($this->normalizeRelationships($entity, $values));
        $this->context->save();
        return $this->jsonResult($object->jsonSerialize());
    }
}
