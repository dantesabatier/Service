<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\BatchUpdateRequest;
use Sabatier\CoreData\BatchUpdateRequestResultType;
use Sabatier\CoreData\BatchUpdateResult;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class BatchUpdateTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "batch_update";
    }
    #[Override]
    public string $description {
        get => <<<DESC
        Update multiple entities matching an optional predicate in a single operation without loading objects into memory.
        Values may be literal values or database expressions.
        Returns the number of updated records.
        DESC;
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."],
                "predicate" => ["type" => "string"],
                "arguments" => ["type" => "array"],
                "values" => ["type" => "object"],
            ],
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
        /** @var string $entityName */
        $entityName = $arguments["entity"] ?? fatal_error("entity is required");
        /** @var Dictionary<mixed> $values */
        $values = $arguments["values"] ?? fatal_error("values is required");
        /** @var ArrayClass<mixed> $params */
        $params = $arguments["arguments"] ?? new ArrayClass();
        $request = new BatchUpdateRequest(EntityDescription::entity($entityName, $this->context));
        $request->resultType = BatchUpdateRequestResultType::count;
        $request->propertiesToUpdate = $values;
        if ($predicate = $arguments["predicate"]) {
            $this->validatePredicateKeyPaths($entityName, $predicate, $params);
            $request->predicate = $this->buildPredicate($predicate, $params);
        }
        /** @var BatchUpdateResult $result */
        $result = $this->context->execute($request);
        return $this->jsonResult(["updated" => $result->result]);
    }
}
