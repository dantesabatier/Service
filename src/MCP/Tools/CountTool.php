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
    public bool $isReadOnly {
        get => true;
    }

    #[Override]
    public bool $isOpenWorld {
        get => false;
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
     * @throws Exception
     */
    #[Override]
    public function authorizationRequirements(Dictionary $arguments): ?AuthorizationRequirements
    {
        /** @var string $entity */
        $entity = $arguments["entity"] ?? fatal_error("entity is required");
        return AuthorizationRequirements::of($this->readRequirements($entity, $this->predicateKeyPaths($arguments["predicate"], $arguments["arguments"])));
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
        $request->predicate = $this->predicateFromArguments($entity, $arguments["predicate"], $arguments["arguments"]);
        $this->applySecurityScope($request);
        return $this->jsonResult(["count" => $this->context->count($request)]);
    }
}
