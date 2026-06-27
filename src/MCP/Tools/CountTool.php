<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class CountTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "count";
    }
    #[Override]
    public string $description {
        get => "Count entities matching an optional predicate.";
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
            "required" => ["entity"],
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
        $request = $this->fetchRequest($entity);
        /** @var string|null $predicate */
        $predicate = $arguments["predicate"];
        /** @var ArrayClass<mixed> $params */
        $params = $arguments["arguments"] ?? new ArrayClass();
        if ($predicate) {
            $this->validatePredicateKeyPaths($entity, $predicate, $params);
            $request->predicate = $this->buildPredicate($predicate, $params);
        }
        return $this->jsonResult(["count" => $this->context->count($request)]);
    }
}
