<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\BatchDeleteRequest;
use Sabatier\CoreData\BatchDeleteRequestResultType;
use Sabatier\CoreData\BatchDeleteResult;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class BatchDeleteTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "batch_delete";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => [
                "entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."],
                "predicate" => ["type" => "string"],
                "arguments" => ["type" => "array"],
            ],
            "required" => ["entity", "predicate"],
        ];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        $entityName = $arguments["entity"] ?? fatal_error("entity is required");
        $predicate = $arguments["predicate"] ?? fatal_error("predicate is required");
        $params = $arguments["arguments"] ?? new ArrayClass();
        $this->validatePredicateKeyPaths($entityName, $predicate, $params);
        $fetchRequest = $this->fetchRequest($entityName);
        $fetchRequest->predicate = $this->buildPredicate($predicate, $params);
        $request = new BatchDeleteRequest($fetchRequest);
        $request->resultType = BatchDeleteRequestResultType::count;
        /** @var BatchDeleteResult $result */
        $result = $this->context->execute($request);
        return $this->jsonResult(["deleted" => $result->result]);
    }
}
