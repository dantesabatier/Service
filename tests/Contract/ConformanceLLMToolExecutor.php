<?php

declare(strict_types=1);

namespace Sabatier\Service\Testing;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMToolExecutionResult;
use Sabatier\Service\LLM\LLMToolExecutor;
use Sabatier\Service\MCP\Response\ToolDescriptor;

/** A deterministic executor fixture that records whether a loop crossed the guarded tool boundary. */
final class ConformanceLLMToolExecutor implements LLMToolExecutor
{
    /** @var ArrayClass<ToolDescriptor> The catalogue advertised by the conformance scenario. */
    #[Override]
    public ArrayClass $tools {
        get => $this->tools ??= new ArrayClass([new ToolDescriptor("external_tool", "Read or change external state.", ["type" => "object"])]);
    }
    /** @var ArrayClass<LLMToolCall> The concrete invocations that reached execution. */
    public readonly ArrayClass $calls;

    /** @param bool $readOnly Whether the reference call bypasses write approval. */
    public function __construct(private readonly bool $readOnly = true)
    {
        $this->calls = new ArrayClass();
    }

    #[Override]
    public function contains(LLMToolCall $call): bool
    {
        return $call->name === "external_tool";
    }

    #[Override]
    public function isReadOnly(LLMToolCall $call): bool
    {
        return $this->readOnly;
    }

    #[Override]
    public function isCacheable(LLMToolCall $call): bool
    {
        return false;
    }

    #[Override]
    public function execute(LLMToolCall $call, ?LLMExecutionDeadline $deadline = null): LLMToolExecutionResult
    {
        $this->calls->append($call);
        return new LLMToolExecutionResult("tool result");
    }
}
