<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;

/**
 * Holds the resolved MCP tools and dispatches calls by name.
 *
 * Exposes the tool catalogue as `$list` (`ArrayClass<ToolDescriptor>`) for the LLM
 * client, and dispatches `call()` to the matching tool implementation.
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
        get => $this->list ??= $this->tools->map(fn(AbstractTool $tool) => new ToolDescriptor($tool->name, $tool->description, $tool->inputSchema, $tool->title));
    }

    /** @param ArrayClass<AbstractTool> $toolList */
    public function __construct(private readonly ArrayClass $toolList)
    {
    }

    /**
     * Invokes a tool by name and never lets it throw past this point.
     *
     * This is the single funnel both MCP and the in-process agent loop converge on. A tool that
     * mis-fires on the LLM's bad input throws an `InternalInconsistencyException` (via `fatal_error`)
     * carrying a message written for the model; that is captured and returned as a failed
     * `ToolResult` so the caller can feed it back for correction, and logged once here so the
     * programmer also learns the LLM is misbehaving. Any other `Throwable` is a real program fault,
     * not something the model can fix, and propagates to the caller.
     *
     * @param string $name The name of the tool to invoke.
     * @param Dictionary<mixed> $arguments The arguments supplied by the model for the call.
     * @return ToolResult The tool's content on success, or a correctable failure carrying the message for the model.
     * @throws Throwable A fatal program fault (anything other than an `InternalInconsistencyException`) raised by the tool.
     */
    public function call(string $name, Dictionary $arguments): ToolResult
    {
        try {
            /** @var AbstractTool $tool */
            $tool = $this->tools[$name] ?? throw new ToolNotFoundException("Unknown tool: $name");
            return ToolResult::success($tool->execute($arguments));
        } catch (InternalInconsistencyException $exception) {
            error_log((string)$exception);
            return ToolResult::failure($exception->getMessage());
        }
    }
}
