<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Tools\ToolRegistry;

/** Executes agent tools synchronously through the framework's MCP registry, without adding process isolation or interruption. */
final class InProcessLLMToolExecutor implements LLMToolExecutor
{
    #[Override]
    public ArrayClass $tools {
        get => $this->registry->list;
    }

    /** @param ToolRegistry $registry The in-process catalogue and dispatch funnel to adapt. */
    public function __construct(private readonly ToolRegistry $registry)
    {
    }

    #[Override]
    public function contains(LLMToolCall $call): bool
    {
        return $this->registry->isRegistered($call->name);
    }

    #[Override]
    public function isReadOnly(LLMToolCall $call): bool
    {
        return $this->registry->isReadOnlyCall($call->name, $call->arguments);
    }

    #[Override]
    public function isCacheable(LLMToolCall $call): bool
    {
        return $this->registry->isCacheable($call->name);
    }

    #[Override]
    public function execute(LLMToolCall $call): LLMToolExecutionResult
    {
        $result = $this->registry->call($call->name, $call->arguments);
        return new LLMToolExecutionResult($result->text, $result->isError);
    }
}
