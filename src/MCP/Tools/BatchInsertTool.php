<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\CoreData\BatchInsertRequest;
use Sabatier\CoreData\BatchInsertRequestResultType;
use Sabatier\CoreData\BatchInsertResult;
use Sabatier\CoreData\EntityDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class BatchInsertTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "batch_insert";
    }
    #[Override]
    public string $description {
        get => "Insert multiple rows in one operation.";
    }
    #[Override]
    public array $inputSchema {
        get => [
            "type" => "object",
            "properties" => ["entity" => ["type" => "string", "description" => "Always required. Entity name from the data model — call describe_model first if unsure."], "objects" => ["type" => "array"]],
            "required" => ["entity", "objects"],
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
        $objects = $arguments["objects"] ?? fatal_error("objects is required");
        $this->assertConcreteEntity($entity);
        $index = 0;
        $total = count($objects);
        $request = new BatchInsertRequest(
            EntityDescription::entity($entity, $this->context),
            dictionaryHandler: function (Dictionary $row) use ($objects, &$index, $total): bool {
                if ($index >= $total) {
                    return false;
                }
                foreach ($objects[$index] as $key => $value) {
                    $row[$key] = $value;
                }
                $index++;
                return true;
            },
            resultType: BatchInsertRequestResultType::count
        );
        /** @var BatchInsertResult $result */
        $result = $this->context->execute($request);
        return $this->jsonResult(["inserted" => $result->result]);
    }
}
