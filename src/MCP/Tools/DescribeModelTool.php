<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use JsonException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use stdClass;

/** @internal */
final class DescribeModelTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "describe_model";
    }
    #[Override]
    public string $description {
        get => "Return the complete schema of all entities, attributes, relationships, and enum cases. Call this before any other tool — attribute names are system-specific and cannot be guessed (e.g. isEnabled, not isActive).";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object", "properties" => new stdClass(), "required" => []];
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult($this->descriptor->schema);
    }
}
