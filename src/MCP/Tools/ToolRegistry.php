<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use function Sabatier\Foundation\fatal_error;

/**
 * Holds the resolved MCP tools and dispatches calls by name.
 *
 * Exposes the tool catalogue as `$list` (`ArrayClass<ToolDescriptor>`) for LLM
 * clients to include in their requests, and dispatches `call()` to the matching
 * tool implementation.
 */
final class ToolRegistry
{
    /** @var Dictionary<AbstractTool> */
    private Dictionary $tools {
        get => $this->tools ??= $this->toolList->reduce(new Dictionary(),
            /**
             * @param Dictionary<AbstractTool> $carry
             * @return Dictionary<AbstractTool>
             */
            function (Dictionary $carry, AbstractTool $tool) {
                $carry[$tool->name] = $tool;
                return $carry;
            });
    }
    /** @var ArrayClass<ToolDescriptor> */
    public ArrayClass $list {
        get => $this->list ??= $this->tools->map(fn(AbstractTool $tool) => new ToolDescriptor($tool->name, $tool->description, $tool->inputSchema));
    }

    /** @param ArrayClass<AbstractTool> $toolList */
    public function __construct(private readonly ArrayClass $toolList)
    {
    }

    /**
     * @param string $name
     * @param Dictionary<mixed> $arguments
     * @return ArrayClass<ContentItem>
     */
    public function call(string $name, Dictionary $arguments): ArrayClass
    {
        /** @var AbstractTool $tool */
        $tool = $this->tools[$name] ?? fatal_error("Unknown tool: $name");
        return $tool->execute($arguments);
    }
}
